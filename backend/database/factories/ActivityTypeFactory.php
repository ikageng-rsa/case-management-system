<?php

namespace Database\Factories;

use App\Enums\Narration\ActivityMeasure;
use App\Models\ActivityType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityType>
 */
class ActivityTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->lexify('????'),
            'measure' => ActivityMeasure::Minutes,
            'increment' => 6,
            'default_billable' => true,
            'requires_court' => false,
        ];
    }

    public function measuredIn(ActivityMeasure $measure, int $increment = 1): static
    {
        return $this->state(fn (array $attributes) => [
            'measure' => $measure,
            'increment' => $increment,
        ]);
    }

    public function appearance(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => 'APP',
            'requires_court' => true,
        ]);
    }

    public function nonBillable(): static
    {
        return $this->state(fn (array $attributes) => [
            'default_billable' => false,
        ]);
    }
}
