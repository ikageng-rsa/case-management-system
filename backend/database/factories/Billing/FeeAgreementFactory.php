<?php

declare(strict_types=1);

namespace Database\Factories\Billing;

use App\Enums\Billing\FeeAgreementKind;
use App\Enums\Billing\FeeAgreementStatus;
use App\Models\Billing\FeeAgreement;
use App\Models\Matter\Matter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeeAgreement>
 */
class FeeAgreementFactory extends Factory
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
            'kind' => FeeAgreementKind::Mandate,
            'percent_hundredths' => null,
            'status' => FeeAgreementStatus::Active,
            'signed_on' => now()->toDateString(),
        ];
    }

    public function contingency(float $percent = 25): static
    {
        return $this->state(fn (array $attributes) => ['kind' => FeeAgreementKind::Contingency, 'percent_hundredths' => (int) round($percent * 100)]);
    }

    public function estate(float $percent = 3.5): static
    {
        return $this->state(fn (array $attributes) => ['kind' => FeeAgreementKind::Estate, 'percent_hundredths' => (int) round($percent * 100)]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => ['status' => FeeAgreementStatus::Failed, 'closed_at' => now()]);
    }

    public function finalised(int $amountReceivedCents = 48000000): static
    {
        return $this->state(fn (array $attributes) => ['status' => FeeAgreementStatus::Finalised, 'amount_received_cents' => $amountReceivedCents, 'closed_at' => now()]);
    }
}
