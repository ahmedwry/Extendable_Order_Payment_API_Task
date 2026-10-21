<?php

namespace App\Payments\Gateways;

use Illuminate\Support\Str;

class PaypalGateway extends SimulatedGateway
{
    public function __construct()
    {
        parent::__construct('paypal');
    }

    protected function reference(): string
    {
        return 'pp_'.Str::uuid();
    }
}
