<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory()->confirmed(),
            'method' => 'credit_card',
            'status' => PaymentStatus::Pending,
            'amount' => fake()->randomFloat(2, 1, 500),
            'gateway_reference' => null,
        ];
    }

    public function successful(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PaymentStatus::Successful,
            'gateway_reference' => fake()->uuid(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PaymentStatus::Failed,
        ]);
    }

    public function paypal(): static
    {
        return $this->state(fn (array $attributes): array => [
            'method' => 'paypal',
        ]);
    }
}
