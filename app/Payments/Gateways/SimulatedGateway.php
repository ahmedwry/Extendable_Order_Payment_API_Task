<?php

namespace App\Payments\Gateways;

use App\Payments\ChargeRequest;
use App\Payments\GatewayCharge;
use App\Payments\PaymentGateway;

abstract class SimulatedGateway implements PaymentGateway
{
    public function __construct(private string $method) {}

    public function charge(ChargeRequest $charge): GatewayCharge
    {
        if ($charge->decline || ! $this->credentialsAreConfigured()) {
            return GatewayCharge::failed();
        }

        return GatewayCharge::successful($this->reference());
    }

    abstract protected function reference(): string;

    private function credentialsAreConfigured(): bool
    {
        $key = config("payments.gateways.{$this->method}.key");
        $secret = config("payments.gateways.{$this->method}.secret");

        return is_string($key) && $key !== ''
            && is_string($secret) && $secret !== '';
    }
}
