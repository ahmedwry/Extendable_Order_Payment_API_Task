<?php

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('allows the owner to view, update, delete, and pay the order', function (string $ability) {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->create();

    $response = Gate::forUser($user)->inspect($ability, $order);

    expect($response->allowed())->toBeTrue();
})->with([
    'view',
    'update',
    'delete',
    'pay',
]);

it('denies another user with a not found response', function (string $ability) {
    $order = Order::factory()->create();
    $other = User::factory()->create();

    $response = Gate::forUser($other)->inspect($ability, $order);

    expect($response->denied())->toBeTrue();
    expect($response->status())->toBe(404);
})->with([
    'view',
    'update',
    'delete',
    'pay',
]);
