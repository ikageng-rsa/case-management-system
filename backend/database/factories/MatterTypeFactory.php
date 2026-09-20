<?php

namespace Database\Factories;

use App\Models\MatterType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MatterType>
 */
class MatterTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->lexify('???'),
            'name' => fake()->words(2, true),
            'default_prescription_months' => 36,
            'is_litigation' => true,
        ];
    }

    public function roadAccidentFund(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => 'RAF',
            'name' => 'Road Accident Fund',
            'default_prescription_months' => 36,
            'is_litigation' => true,
        ]);
    }

    public function nonLitigation(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_litigation' => false,
            'default_prescription_months' => null,
        ]);
    }
}
