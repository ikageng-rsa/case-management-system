<?php

namespace Database\Factories;

use App\Models\DiaryEntry;
use App\Models\Matter;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiaryEntry>
 */
class DiaryEntryFactory extends Factory
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
            'assigned_to' => User::factory(),
            'body' => fake()->sentence(),
            'due_at' => now()->addWeek(),
        ];
    }

    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'due_at' => now()->subDay(),
        ]);
    }

    public function dueIn(int $days): static
    {
        return $this->state(fn (array $attributes) => [
            'due_at' => now()->addDays($days),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'completed_at' => now(),
            'completed_by' => User::factory(),
        ]);
    }
}
