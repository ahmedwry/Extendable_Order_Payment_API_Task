# Order and Payment API

A Laravel API for orders and simulated payments. Each payment method is a gateway class, so a new method can be added without changing the payment controller.

## Requirements

- PHP 8.3 or newer
- Composer
- MySQL

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Create the MySQL database, then migrate:

```sql
CREATE DATABASE extendable_order_payment_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

```bash
php artisan migrate
```

`.env.example` already points at that database on `127.0.0.1:3306` as `root`. Change `DB_USERNAME` and `DB_PASSWORD` if your MySQL account is different.

Set the gateway credentials in `.env`. A charge is stored as `failed` when the key or secret for that method is empty.

```dotenv
PAYMENT_CREDIT_CARD_KEY=card-key
PAYMENT_CREDIT_CARD_SECRET=card-secret
PAYMENT_PAYPAL_KEY=paypal-key
PAYMENT_PAYPAL_SECRET=paypal-secret
```

Start the app:

```bash
php artisan serve
```

The API base URL is `http://localhost:8000/api`.

Run the tests:

```bash
php artisan test
```

## Authentication

`POST /api/register` and `POST /api/login` return a bearer token. Send it on later requests:

```http
Authorization: Bearer {token}
Accept: application/json
```

A missing or invalid token returns `401`.

## Endpoints

| Method | Path | Success |
| --- | --- | --- |
| POST | `/api/register` | `201` |
| POST | `/api/login` | `200` |
| GET | `/api/user` | `200` |
| POST | `/api/logout` | `204` |
| POST | `/api/orders` | `201` |
| GET | `/api/orders` | `200` |
| GET | `/api/orders/{order}` | `200` |
| PUT | `/api/orders/{order}` | `200` |
| DELETE | `/api/orders/{order}` | `204` |
| POST | `/api/orders/{order}/payments` | `201` |
| GET | `/api/orders/{order}/payments` | `200` |
| GET | `/api/payments` | `200` |

List routes accept `per_page` from 1 to 100. The default is 15. `GET /api/orders` also accepts `status`: `pending`, `confirmed`, or `cancelled`.

An order is created as `pending`. Its total is the sum of each item's quantity times unit price. `DELETE` returns `409` when the order already has a payment. A payment returns `409` unless the order is `confirmed`.

Import `postman/order-payment-api.postman_collection.json` for a success and error example of each request.

## Adding a payment gateway

`ProcessPayment` depends on the `PaymentGateway` interface. `PaymentGatewayManager` resolves the class from `config/payments.php` using the request `method`. The controller does not mention individual gateways.

1. Add a class that extends `App\Payments\Gateways\SimulatedGateway`. The parent succeeds when the gateway key and secret are set, and returns `failed` when `decline` is true or those values are empty.

```php
<?php

namespace App\Payments\Gateways;

use Illuminate\Support\Str;

class BankTransferGateway extends SimulatedGateway
{
    public function __construct()
    {
        parent::__construct('bank_transfer');
    }

    protected function reference(): string
    {
        return 'bt_'.Str::uuid();
    }
}
```

A real provider implements `App\Payments\PaymentGateway` directly and writes its own `charge()` method instead of extending `SimulatedGateway`.

2. Register the class in `config/payments.php`:

```php
'bank_transfer' => [
    'class' => BankTransferGateway::class,
    'key' => env('PAYMENT_BANK_TRANSFER_KEY'),
    'secret' => env('PAYMENT_BANK_TRANSFER_SECRET'),
],
```

3. Add `PAYMENT_BANK_TRANSFER_KEY` and `PAYMENT_BANK_TRANSFER_SECRET` to `.env`.

`POST /api/orders/{order}/payments` then accepts `"method": "bank_transfer"`. The allowed methods are the keys of `config('payments.gateways')`.

## Assumptions

- Authentication uses Laravel Sanctum bearer tokens. The task text asks for JWT. These tokens are random values stored in `personal_access_tokens`, not signed JWTs.
- Register and login identify the user. An order stores that user's id. The create-order body does not accept a separate name or email.
- Order and payment lists return records for the logged-in user. A request for another user's order returns `404`.
- A processed payment is stored as `successful` or `failed`. The `pending` status exists for the payment record, and this synchronous charge does not leave a row in `pending`.
- A confirmed order can have more than one payment.
- `"decline": true` on the payment request simulates a failed charge without calling an external provider.
- Gateway credentials live in `.env`, not in the database.
# Extendable_Order_Payment_API_Task
# Extendable_Order_Payment_API_Task
