<?php

declare(strict_types=1);

namespace Database\Factories\Billing;

use App\Models\Billing\TariffSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TariffSchedule>
 */
class TariffScheduleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Magistrates\' Courts tariff '.fake()->unique()->year(),
            'effective_from' => fake()->unique()->dateTimeBetween('-5 years', '-1 day')->format('Y-m-d'),
            'published_at' => now(),
        ];
    }

    public function startingOn(string $date): static
    {
        return $this->state(fn (array $attributes) => ['effective_from' => $date]);
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => ['published_at' => null]);
    }

    public function upcoming(): static
    {
        return $this->state(fn (array $attributes) => ['effective_from' => today()->addMonth()->toDateString()]);
    }
}
