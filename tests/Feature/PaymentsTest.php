<?php

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'payments.gateways.credit_card.key' => 'card-key',
        'payments.gateways.credit_card.secret' => 'card-secret',
        'payments.gateways.paypal.key' => 'paypal-key',
        'payments.gateways.paypal.secret' => 'paypal-secret',
    ]);
});

it('stores a successful payment for a confirmed order', function (string $method, string $prefix) {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->confirmed()->create([
        'total' => '26.00',
    ]);

    $response = $this->withToken($user->createToken('api')->plainTextToken)
        ->postJson('/api/orders/'.$order->id.'/payments', [
            'method' => $method,
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.order_id', $order->id)
        ->assertJsonPath('data.method', $method)
        ->assertJsonPath('data.status', 'successful')
        ->assertJsonPath('data.amount', '26.00');

    expect($response->json('data.gateway_reference'))->toStartWith($prefix);

    $payment = Payment::query()->whereBelongsTo($order)->first();

    expect($payment)->not->toBeNull()
        ->and($payment->status->value)->toBe('successful')
        ->and($payment->amount)->toBe('26.00');
})->with([
    'credit card' => ['credit_card', 'cc_'],
    'paypal' => ['paypal', 'pp_'],
]);

it('stores a failed payment when the charge is declined', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->confirmed()->create([
        'total' => '26.00',
    ]);

    $response = $this->withToken($user->createToken('api')->plainTextToken)
        ->postJson('/api/orders/'.$order->id.'/payments', [
            'method' => 'credit_card',
            'decline' => true,
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'failed')
        ->assertJsonPath('data.gateway_reference', null);
});

it('returns 409 when the order is not confirmed', function (string $state) {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->{$state}()->create();

    $response = $this->withToken($user->createToken('api')->plainTextToken)
        ->postJson('/api/orders/'.$order->id.'/payments', [
            'method' => 'credit_card',
        ]);

    $response->assertConflict()
        ->assertJsonPath('message', 'Payments can only be processed for confirmed orders.');

    expect($order->payments()->count())->toBe(0);
})->with([
    'pending' => 'pending',
    'cancelled' => 'cancelled',
]);

it('returns 422 when the payment method is invalid', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->confirmed()->create();

    $response = $this->withToken($user->createToken('api')->plainTextToken)
        ->postJson('/api/orders/'.$order->id.'/payments', [
            'method' => 'cash',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors([
            'method' => 'The selected method is invalid.',
        ]);
});

it('returns 422 when the payment method is missing', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->confirmed()->create();

    $response = $this->withToken($user->createToken('api')->plainTextToken)
        ->postJson('/api/orders/'.$order->id.'/payments', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors([
            'method' => 'The method field is required.',
        ]);
});

it('lists payments for one order', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->confirmed()->create();
    $payment = Payment::factory()->for($order)->successful()->create();
    Payment::factory()->successful()->create();

    $response = $this->withToken($user->createToken('api')->plainTextToken)
        ->getJson('/api/orders/'.$order->id.'/payments');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $payment->id);
});

it('lists only the authenticated user payments', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->confirmed()->create();
    $payment = Payment::factory()->for($order)->successful()->create();
    Payment::factory()->successful()->create();

    $response = $this->withToken($user->createToken('api')->plainTextToken)
        ->getJson('/api/payments');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $payment->id);
});

it('paginates the payment list', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->confirmed()->create();
    Payment::factory()->count(16)->for($order)->create();

    $response = $this->withToken($user->createToken('api')->plainTextToken)
        ->getJson('/api/payments?per_page=15');

    $response->assertOk()
        ->assertJsonCount(15, 'data')
        ->assertJsonPath('meta.per_page', 15)
        ->assertJsonPath('meta.total', 16);
});

it('returns 404 when paying another user order', function () {
    $order = Order::factory()->confirmed()->create();
    $other = User::factory()->create();

    $this->withToken($other->createToken('api')->plainTextToken)
        ->postJson('/api/orders/'.$order->id.'/payments', [
            'method' => 'credit_card',
        ])
        ->assertNotFound();
});

it('returns 401 when no token is provided', function (string $method, string $uri) {
    $this->json($method, $uri)->assertUnauthorized();
})->with([
    'list' => ['GET', '/api/payments'],
    'order list' => ['GET', '/api/orders/1/payments'],
    'create' => ['POST', '/api/orders/1/payments'],
]);
