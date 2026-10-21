<?php

namespace App\Payments;

use App\Models\Order;

final readonly class ChargeRequest
{
    public function __construct(
        public Order $order,
        public bool $decline = false,
    ) {}
}
