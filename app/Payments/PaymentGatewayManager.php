<?php

namespace App\Payments;

use InvalidArgumentException;

class PaymentGatewayManager
{
    public function gateway(string $method): PaymentGateway
    {
        $class = config("payments.gateways.{$method}.class");

        if (! is_string($class) || ! is_subclass_of($class, PaymentGateway::class)) {
            throw new InvalidArgumentException("Payment gateway [{$method}] is not registered.");
        }

        return app($class);
    }
}
