<?php

namespace App\Payments;

interface PaymentGateway
{
    public function charge(ChargeRequest $charge): GatewayCharge;
}
