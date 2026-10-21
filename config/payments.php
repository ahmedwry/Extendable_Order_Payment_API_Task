<?php

use App\Payments\Gateways\CreditCardGateway;
use App\Payments\Gateways\PaypalGateway;

return [
    'gateways' => [
        'credit_card' => [
            'class' => CreditCardGateway::class,
            'key' => env('PAYMENT_CREDIT_CARD_KEY'),
            'secret' => env('PAYMENT_CREDIT_CARD_SECRET'),
        ],
        'paypal' => [
            'class' => PaypalGateway::class,
            'key' => env('PAYMENT_PAYPAL_KEY'),
            'secret' => env('PAYMENT_PAYPAL_SECRET'),
        ],
    ],
];
