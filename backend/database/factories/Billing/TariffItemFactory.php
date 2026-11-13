<?php

declare(strict_types=1);

namespace Database\Factories\Billing;

use App\Models\Billing\TariffItem;
use App\Models\Billing\TariffSchedule;
use App\Models\Reference\ActivityType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TariffItem>
 */
class TariffItemFactory extends Factory
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
            'activity_type_id' => ActivityType::factory(),
            'scale_a_cents' => 4900,
            'scale_b_cents' => null,
            'scale_c_cents' => null,
            'scale_d_cents' => null,
            'time_rate_cents' => null,
            'time_block_minutes' => null,
        ];
    }
}
