<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('registers a user and returns a token', function () {
    $response = $this->postJson('/api/register', [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertCreated()
        ->assertJsonPath('user.name', 'Ada Lovelace')
        ->assertJsonPath('user.email', 'ada@example.com')
        ->assertJsonMissingPath('user.password')
        ->assertJsonStructure([
            'token',
            'user' => ['id', 'name', 'email'],
        ]);

    $user = User::query()->where('email', 'ada@example.com')->first();

    expect($user)->not->toBeNull()
        ->and(Hash::check('password', $user->password))->toBeTrue();
});

it('returns 422 when registration fields are missing', function () {
    $response = $this->postJson('/api/register', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors([
            'name' => 'The name field is required.',
            'email' => 'The email field is required.',
            'password' => 'The password field is required.',
        ]);
});

it('returns 422 when registration input is invalid', function (array $payload, string $field, string $message) {
    $response = $this->postJson('/api/register', $payload);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors([
            $field => $message,
        ]);
})->with([
    'name must be a string' => [
        [
            'name' => ['Ada'],
            'email' => 'ada@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ],
        'name',
        'The name field must be a string.',
    ],
    'name is too long' => [
        [
            'name' => str_repeat('a', 256),
            'email' => 'ada@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ],
        'name',
        'The name field must not be greater than 255 characters.',
    ],
    'email must be a string' => [
        [
            'name' => 'Ada Lovelace',
            'email' => ['ada@example.com'],
            'password' => 'password',
            'password_confirmation' => 'password',
        ],
        'email',
        'The email field must be a string.',
    ],
    'email must be valid' => [
        [
            'name' => 'Ada Lovelace',
            'email' => 'not-an-email',
            'password' => 'password',
            'password_confirmation' => 'password',
        ],
        'email',
        'The email field must be a valid email address.',
    ],
    'email is too long' => [
        [
            'name' => 'Ada Lovelace',
            'email' => str_repeat('a', 244).'@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ],
        'email',
        'The email field must not be greater than 255 characters.',
    ],
    'password is too short' => [
        [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ],
        'password',
        'The password field must be at least 8 characters.',
    ],
    'password confirmation does not match' => [
        [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'password',
            'password_confirmation' => 'different',
        ],
        'password',
        'The password field confirmation does not match.',
    ],
]);

it('returns 422 when the email is already registered', function () {
    User::factory()->create([
        'email' => 'ada@example.com',
    ]);

    $response = $this->postJson('/api/register', [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors([
            'email' => 'The email has already been taken.',
        ]);
});

it('logs in a user and returns a token', function () {
    $user = User::factory()->create([
        'email' => 'ada@example.com',
        'password' => 'password',
    ]);

    $response = $this->postJson('/api/login', [
        'email' => 'ada@example.com',
        'password' => 'password',
    ]);

    $response->assertOk()
        ->assertJsonPath('user.id', $user->id)
        ->assertJsonPath('user.email', 'ada@example.com')
        ->assertJsonMissingPath('user.password')
        ->assertJsonStructure([
            'token',
            'user' => ['id', 'name', 'email'],
        ]);
});

it('returns 422 when login fields are missing', function () {
    $response = $this->postJson('/api/login', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors([
            'email' => 'The email field is required.',
            'password' => 'The password field is required.',
        ]);
});

it('returns 422 when login input is invalid', function (array $payload, string $field, string $message) {
    $response = $this->postJson('/api/login', $payload);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors([
            $field => $message,
        ]);
})->with([
    'email must be a string' => [
        ['email' => ['ada@example.com'], 'password' => 'password'],
        'email',
        'The email field must be a string.',
    ],
    'email must be valid' => [
        ['email' => 'not-an-email', 'password' => 'password'],
        'email',
        'The email field must be a valid email address.',
    ],
    'password must be a string' => [
        ['email' => 'ada@example.com', 'password' => ['password']],
        'password',
        'The password field must be a string.',
    ],
]);

it('returns 401 when the login credentials do not match', function (array $payload) {
    User::factory()->create([
        'email' => 'ada@example.com',
        'password' => 'password',
    ]);

    $response = $this->postJson('/api/login', $payload);

    $response->assertUnauthorized()
        ->assertJsonPath('message', 'These credentials do not match our records.');
})->with([
    'unknown email' => [['email' => 'missing@example.com', 'password' => 'password']],
    'wrong password' => [['email' => 'ada@example.com', 'password' => 'wrong-password']],
]);

it('returns 401 when the token is missing', function () {
    $this->getJson('/api/user')->assertUnauthorized();
});

it('returns 401 when the token is invalid', function () {
    $this->withToken('invalid-token')->getJson('/api/user')->assertUnauthorized();
});

it('returns the authenticated user', function () {
    $user = User::factory()->create();
    $token = $user->createToken('api')->plainTextToken;

    $response = $this->withToken($token)->getJson('/api/user');

    $response->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.email', $user->email)
        ->assertJsonMissingPath('data.password');
});

it('revokes the current token on logout', function () {
    $user = User::factory()->create();
    $token = $user->createToken('api')->plainTextToken;

    $this->withToken($token)->postJson('/api/logout')->assertNoContent();

    $this->assertDatabaseCount('personal_access_tokens', 0);

    $this->app['auth']->forgetGuards();

    $this->withToken($token)->getJson('/api/user')->assertUnauthorized();
});
