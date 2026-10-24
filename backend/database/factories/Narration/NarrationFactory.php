<?php

declare(strict_types=1);

namespace Database\Factories\Narration;

use App\Models\Matter\Matter;
use App\Models\Narration\Narration;
use App\Models\Reference\ActivityType;
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
        ]);
    }

    public function occurredAt(mixed $when): static
    {
        return $this->state(fn (array $attributes) => [
            'occurred_at' => $when,
        ]);
    }
}
