<?php

namespace App\Payments;

use App\Models\Order;
use App\Models\Payment;

class ProcessPayment
{
    public function __construct(private PaymentGatewayManager $gateways) {}

    public function handle(Order $order, string $method, bool $decline = false): Payment
    {
        if (! $order->isConfirmed()) {
            throw new OrderNotConfirmedException;
        }

        $charge = $this->gateways
            ->gateway($method)
            ->charge(new ChargeRequest($order, $decline));

        return $order->payments()->create([
            'method' => $method,
            'status' => $charge->status,
            'amount' => $order->total,
            'gateway_reference' => $charge->reference,
        ]);
    }
}
