<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('stores the order total as the sum of its item line totals', function () {
    $order = Order::factory()->create();

    OrderItem::factory()->for($order)->create([
        'quantity' => 2,
        'unit_price' => '10.50',
    ]);
    OrderItem::factory()->for($order)->create([
        'quantity' => 1,
        'unit_price' => '5.00',
    ]);

    $order->recalculateTotal();

    expect($order->refresh()->total)->toBe('26.00');
});

it('sets the order total to zero when the order has no items', function () {
    $order = Order::factory()->create([
        'total' => '15.00',
    ]);

    $order->recalculateTotal();

    expect($order->refresh()->total)->toBe('0.00');
});

it('reports an item line total as quantity times unit price', function () {
    $item = OrderItem::factory()->create([
        'quantity' => 3,
        'unit_price' => '4.25',
    ]);

    expect($item->line_total)->toBe('12.75');
});

it('reports whether the order is confirmed', function (string $state, bool $confirmed) {
    $order = Order::factory()->{$state}()->create();

    expect($order->isConfirmed())->toBe($confirmed);
})->with([
    'pending' => ['pending', false],
    'confirmed' => ['confirmed', true],
    'cancelled' => ['cancelled', false],
]);

it('reports that an order has payments when one exists', function () {
    $order = Order::factory()->confirmed()->create();
    Payment::factory()->for($order)->create();

    expect($order->hasPayments())->toBeTrue();
});

it('reports that an order has no payments when none exist', function () {
    $order = Order::factory()->create();

    expect($order->hasPayments())->toBeFalse();
});

it('removes line items when the order is deleted', function () {
    $order = Order::factory()->create();
    $item = OrderItem::factory()->for($order)->create();

    $order->delete();

    $this->assertModelMissing($order);
    $this->assertModelMissing($item);
});

it('keeps an order that still has a payment', function () {
    $order = Order::factory()->confirmed()->create();
    Payment::factory()->for($order)->create();

    expect(fn () => $order->delete())->toThrow(QueryException::class);

    $this->assertModelExists($order);
});
