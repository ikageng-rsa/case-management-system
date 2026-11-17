<?php

declare(strict_types=1);

namespace Database\Factories\Billing;

use App\Enums\Billing\BillKind;
use App\Enums\Billing\BillStatus;
use App\Models\Billing\Bill;
use App\Models\Matter\Matter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bill>
 */
class BillFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'matter_id' => Matter::factory(),
            'kind' => BillKind::Statement,
            'status' => BillStatus::Draft,
            'addressee' => fake()->name(),
        ];
    }

    public function issued(string $number = 'INV/2026/0001'): static
    {
        return $this->state(fn (array $attributes) => ['status' => BillStatus::Issued, 'number' => $number, 'issued_at' => now()]);
    }

    public function void(): static
    {
        return $this->state(fn (array $attributes) => ['status' => BillStatus::Void, 'voided_at' => now()]);
    }
}
