<?php

namespace App\Payments;

use App\Enums\PaymentStatus;

final readonly class GatewayCharge
{
    public function __construct(
        public PaymentStatus $status,
        public ?string $reference,
    ) {}

    public static function successful(string $reference): self
    {
        return new self(PaymentStatus::Successful, $reference);
    }

    public static function failed(): self
    {
        return new self(PaymentStatus::Failed, null);
    }
}
