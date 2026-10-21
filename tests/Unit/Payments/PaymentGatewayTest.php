<?php

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Payments\ChargeRequest;
use App\Payments\Gateways\CreditCardGateway;
use App\Payments\Gateways\PaypalGateway;
use App\Payments\PaymentGatewayManager;
use Tests\TestCase;

uses(TestCase::class);

it('charges through the gateway configured for the method', function (string $method, string $class, string $prefix) {
    config([
        "payments.gateways.{$method}.key" => 'key',
        "payments.gateways.{$method}.secret" => 'secret',
    ]);

    $gateway = app(PaymentGatewayManager::class)->gateway($method);
    $charge = $gateway->charge(new ChargeRequest(new Order));

    expect($gateway)->toBeInstanceOf($class)
        ->and($charge->status)->toBe(PaymentStatus::Successful)
        ->and($charge->reference)->toStartWith($prefix);
})->with([
    'credit card' => ['credit_card', CreditCardGateway::class, 'cc_'],
    'paypal' => ['paypal', PaypalGateway::class, 'pp_'],
]);

it('fails the charge when the gateway credentials are missing', function (string $method) {
    config([
        "payments.gateways.{$method}.key" => null,
        "payments.gateways.{$method}.secret" => null,
    ]);

    $charge = app(PaymentGatewayManager::class)
        ->gateway($method)
        ->charge(new ChargeRequest(new Order));

    expect($charge->status)->toBe(PaymentStatus::Failed);
    expect($charge->reference)->toBeNull();
})->with([
    'credit card' => 'credit_card',
    'paypal' => 'paypal',
]);

it('fails the charge when the caller declines it', function (string $method) {
    config([
        "payments.gateways.{$method}.key" => 'key',
        "payments.gateways.{$method}.secret" => 'secret',
    ]);

    $charge = app(PaymentGatewayManager::class)
        ->gateway($method)
        ->charge(new ChargeRequest(new Order, decline: true));

    expect($charge->status)->toBe(PaymentStatus::Failed);
    expect($charge->reference)->toBeNull();
})->with([
    'credit card' => 'credit_card',
    'paypal' => 'paypal',
]);

it('rejects a method that is not registered', function () {
    app(PaymentGatewayManager::class)->gateway('cash');
})->throws(InvalidArgumentException::class);
