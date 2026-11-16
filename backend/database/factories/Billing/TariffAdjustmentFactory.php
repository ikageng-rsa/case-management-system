<?php

declare(strict_types=1);

namespace Database\Factories\Billing;

use App\Enums\Billing\TariffAdjustmentKind;
use App\Models\Billing\TariffAdjustment;
use App\Models\Billing\TariffSchedule;
use App\Models\User\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TariffAdjustment>
 */
class TariffAdjustmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tariff_schedule_id' => TariffSchedule::factory(),
            'role_id' => fn () => Role::findOrCreate(fake()->unique()->word())->getKey(),
            'kind' => TariffAdjustmentKind::Percent,
            'value' => 0,
        ];
    }

    /** @param  float  $percent  e.g. -50 for half the tariff */
    public function percent(float $percent): static
    {
        return $this->state(fn (array $attributes) => ['kind' => TariffAdjustmentKind::Percent, 'value' => (int) round($percent * 100)]);
    }

    /** @param  float  $rand  added to every unit, e.g. 15 for R 15.00 */
    public function amount(float $rand): static
    {
        return $this->state(fn (array $attributes) => ['kind' => TariffAdjustmentKind::Amount, 'value' => (int) round($rand * 100)]);
    }
}
