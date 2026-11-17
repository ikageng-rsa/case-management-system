<?php

declare(strict_types=1);

namespace Database\Factories\Client;

use App\Models\Client\Client;
use App\Models\Client\ClientContactPerson;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientContactPerson>
 */
class ClientContactPersonFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory()->entity(),
            'name' => fake()->name(),
            'role' => fake()->jobTitle(),
            'mobile' => fake()->unique()->numerify('0#########'),
            'email' => fake()->unique()->safeEmail(),
            'is_primary' => false,
        ];
    }

    public function primary(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_primary' => true,
        ]);
    }
}
