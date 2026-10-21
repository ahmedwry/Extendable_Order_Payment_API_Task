<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates an order and stores the calculated total', function () {
    $user = User::factory()->create();
    $token = $user->createToken('api')->plainTextToken;

    $response = $this->withToken($token)->postJson('/api/orders', [
        'items' => [
            ['product_name' => 'Widget', 'quantity' => 2, 'unit_price' => '10.50'],
            ['product_name' => 'Cable', 'quantity' => 1, 'unit_price' => '5.00'],
        ],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.total', '26.00')
        ->assertJsonPath('data.items.0.product_name', 'Widget')
        ->assertJsonPath('data.items.0.line_total', '21.00')
        ->assertJsonPath('data.items.1.line_total', '5.00');

    $order = Order::query()->whereBelongsTo($user)->first();

    expect($order)->not->toBeNull()
        ->and($order->total)->toBe('26.00')
        ->and($order->items)->toHaveCount(2);
});

it('returns 422 when creating an order with invalid items', function (array $payload, string $field, string $message) {
    $user = User::factory()->create();

    $response = $this->withToken($user->createToken('api')->plainTextToken)
        ->postJson('/api/orders', $payload);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors([
            $field => $message,
        ]);
})->with([
    'items are missing' => [
        [],
        'items',
        'The items field is required.',
    ],
    'product name is missing' => [
        ['items' => [['quantity' => 1, 'unit_price' => '5.00']]],
        'items.0.product_name',
        'The product name field is required.',
    ],
    'quantity is below 1' => [
        ['items' => [['product_name' => 'Widget', 'quantity' => 0, 'unit_price' => '5.00']]],
        'items.0.quantity',
        'The quantity field must be at least 1.',
    ],
    'price is negative' => [
        ['items' => [['product_name' => 'Widget', 'quantity' => 1, 'unit_price' => '-1']]],
        'items.0.unit_price',
        'The price field must be at least 0.',
    ],
    'price has too many decimal places' => [
        ['items' => [['product_name' => 'Widget', 'quantity' => 1, 'unit_price' => '10.555']]],
        'items.0.unit_price',
        'The price field must have 0-2 decimal places.',
    ],
]);

it('lists only the authenticated user orders for a status', function () {
    $user = User::factory()->create();
    $confirmed = Order::factory()->for($user)->confirmed()->create();
    Order::factory()->for($user)->create();
    Order::factory()->confirmed()->create();

    $response = $this->withToken($user->createToken('api')->plainTextToken)
        ->getJson('/api/orders?status=confirmed');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $confirmed->id)
        ->assertJsonPath('data.0.status', 'confirmed');
});

it('paginates the order list', function () {
    $user = User::factory()->create();
    Order::factory()->count(16)->for($user)->create();

    $response = $this->withToken($user->createToken('api')->plainTextToken)
        ->getJson('/api/orders?per_page=15');

    $response->assertOk()
        ->assertJsonCount(15, 'data')
        ->assertJsonPath('meta.per_page', 15)
        ->assertJsonPath('meta.total', 16);
});

it('returns 422 when the list filters are invalid', function (string $query, string $field, string $message) {
    $user = User::factory()->create();

    $response = $this->withToken($user->createToken('api')->plainTextToken)
        ->getJson('/api/orders?'.$query);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors([
            $field => $message,
        ]);
})->with([
    'unknown status' => ['status=shipped', 'status', 'The selected status is invalid.'],
    'per page below 1' => ['per_page=0', 'per_page', 'The per page field must be at least 1.'],
]);

it('returns one order with its items', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->create();
    OrderItem::factory()->for($order)->create([
        'product_name' => 'Widget',
        'quantity' => 2,
        'unit_price' => '10.50',
    ]);

    $response = $this->withToken($user->createToken('api')->plainTextToken)
        ->getJson('/api/orders/'.$order->id);

    $response->assertOk()
        ->assertJsonPath('data.id', $order->id)
        ->assertJsonPath('data.items.0.product_name', 'Widget')
        ->assertJsonPath('data.items.0.line_total', '21.00');
});

it('updates the order status', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->create();

    $response = $this->withToken($user->createToken('api')->plainTextToken)
        ->putJson('/api/orders/'.$order->id, [
            'status' => 'confirmed',
        ]);

    $response->assertOk()
        ->assertJsonPath('data.status', 'confirmed');

    expect($order->refresh()->status->value)->toBe('confirmed');
});

it('replaces the items and recalculates the total', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->create(['total' => '10.00']);
    OrderItem::factory()->for($order)->create([
        'quantity' => 1,
        'unit_price' => '10.00',
    ]);

    $response = $this->withToken($user->createToken('api')->plainTextToken)
        ->putJson('/api/orders/'.$order->id, [
            'items' => [
                ['product_name' => 'Cable', 'quantity' => 3, 'unit_price' => '4.25'],
            ],
        ]);

    $response->assertOk()
        ->assertJsonPath('data.total', '12.75')
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.product_name', 'Cable');

    expect($order->refresh()->total)->toBe('12.75');
});

it('returns 422 when the updated status is invalid', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->create();

    $response = $this->withToken($user->createToken('api')->plainTextToken)
        ->putJson('/api/orders/'.$order->id, [
            'status' => 'shipped',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors([
            'status' => 'The selected status is invalid.',
        ]);
});

it('deletes an order that has no payments', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->create();
    $item = OrderItem::factory()->for($order)->create();

    $this->withToken($user->createToken('api')->plainTextToken)
        ->deleteJson('/api/orders/'.$order->id)
        ->assertNoContent();

    $this->assertModelMissing($order);
    $this->assertModelMissing($item);
});

it('returns 409 when deleting an order that has a payment', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->confirmed()->create();
    Payment::factory()->for($order)->create();

    $response = $this->withToken($user->createToken('api')->plainTextToken)
        ->deleteJson('/api/orders/'.$order->id);

    $response->assertConflict()
        ->assertJsonPath('message', 'An order with payments cannot be deleted.');

    $this->assertModelExists($order);
});

it('returns 404 when the order belongs to another user', function (string $method) {
    $owner = User::factory()->create();
    $order = Order::factory()->for($owner)->create();
    $other = User::factory()->create();

    $this->withToken($other->createToken('api')->plainTextToken)
        ->json($method, '/api/orders/'.$order->id)
        ->assertNotFound();
})->with([
    'show' => 'GET',
    'update' => 'PUT',
    'delete' => 'DELETE',
]);

it('returns 401 when no token is provided', function (string $method, string $uri) {
    $this->json($method, $uri)->assertUnauthorized();
})->with([
    'list' => ['GET', '/api/orders'],
    'create' => ['POST', '/api/orders'],
    'show' => ['GET', '/api/orders/1'],
    'update' => ['PUT', '/api/orders/1'],
    'delete' => ['DELETE', '/api/orders/1'],
]);
