<?php

declare(strict_types=1);

namespace Database\Factories\Billing;

use App\Enums\Billing\FirmAccount;
use App\Enums\Billing\PaymentMethod;
use App\Models\Billing\Bill;
use App\Models\Billing\Payment;
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
            'bill_id' => Bill::factory()->issued(),
            'amount_cents' => 100000,
            'received_on' => now()->toDateString(),
            'method' => PaymentMethod::Eft,
            'account' => FirmAccount::Business,
        ];
    }

    public function reversed(): static
    {
        return $this->state(fn (array $attributes) => ['reversed_at' => now(), 'reverse_reason' => 'Recorded on the wrong bill']);
    }
}
