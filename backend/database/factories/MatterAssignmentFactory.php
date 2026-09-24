<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Matter\Assignment;
use App\Models\Matter;
use App\Models\MatterAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MatterAssignment>
 */
class MatterAssignmentFactory extends Factory
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
            'user_id' => User::factory(),
            'capacity' => Assignment::Responsible,
            'assigned_at' => now(),
        ];
    }

    public function capacity(Assignment $capacity): static
    {
        return $this->state(fn (array $attributes) => [
            'capacity' => $capacity,
        ]);
    }

    public function unassigned(): static
    {
        return $this->state(fn (array $attributes) => [
            'unassigned_at' => now(),
        ]);
    }
}
