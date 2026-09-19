<?php

namespace Database\Factories;

use App\Models\ActivityType;
use App\Models\Court;
use App\Models\Matter;
use App\Models\Narration;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Narration>
 */
class NarrationFactory extends Factory
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
            'activity_type_id' => ActivityType::factory(),
            'author_id' => User::factory(),
            'body' => fake()->sentence(),
            'quantity' => 30,
            'occurred_at' => now(),
        ];
    }

    public function appearance(): static
    {
        return $this->state(fn (array $attributes) => [
            'activity_type_id' => ActivityType::factory()->appearance(),
            'court_id' => Court::factory(),
        ]);
    }

    public function occurredAt(mixed $when): static
    {
        return $this->state(fn (array $attributes) => [
            'occurred_at' => $when,
        ]);
    }
}
