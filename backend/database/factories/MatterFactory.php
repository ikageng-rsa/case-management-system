<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Client;
use App\Models\Court;
use App\Models\Matter;
use App\Models\MatterType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Matter>
 */
class MatterFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $sequence = fake()->unique()->numberBetween(1, 99999);

        return [
            'client_id' => Client::factory(),
            'matter_type_id' => MatterType::factory(),
            'court_id' => Court::factory(),
            'reference' => "CIV/{$sequence}/".now()->year,
            'sequence_number' => $sequence,
            'opened_year' => now()->year,
            'title' => fake()->sentence(4),
            'instructed_at' => now(),
            'prescribes_at' => now()->addMonths(36),
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'closed_at' => now(),
        ]);
    }

    public function nonLitigation(): static
    {
        return $this->state(fn (array $attributes) => [
            'matter_type_id' => MatterType::factory()->nonLitigation(),
            'court_id' => null,
            'prescribes_at' => null,
        ]);
    }

    public function prescribesIn(int $days): static
    {
        return $this->state(fn (array $attributes) => [
            'prescribes_at' => now()->addDays($days),
        ]);
    }

    public function prescribed(): static
    {
        return $this->state(fn (array $attributes) => [
            'prescribes_at' => now()->subDay(),
        ]);
    }
}
