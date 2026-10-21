<?php

namespace App\Payments\Gateways;

use Illuminate\Support\Str;

class CreditCardGateway extends SimulatedGateway
{
    public function __construct()
    {
        parent::__construct('credit_card');
    }

    protected function reference(): string
    {
        return 'cc_'.Str::uuid();
    }
}
