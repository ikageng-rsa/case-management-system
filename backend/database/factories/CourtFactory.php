<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Court\CourtTier;
use App\Models\Court;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Court>
 */
class CourtFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tier' => CourtTier::High,
            'name' => CourtTier::High->courtName(),
            'seat' => fake()->city(),
        ];
    }

    public function tier(CourtTier $tier): static
    {
        return $this->state(fn (array $attributes) => [
            'tier' => $tier,
            'name' => $tier->courtName(),
        ]);
    }
}
