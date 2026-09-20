<?php

namespace Database\Factories;

use App\Enums\Client\ClientType;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => ClientType::Individual,
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'id_number' => fake()->unique()->numerify('#############'),
        ];
    }

    public function entity(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ClientType::Entity,
            'first_name' => null,
            'last_name' => null,
            'entity_name' => fake()->company(),
            'id_number' => null,
            'registration_number' => fake()->unique()->numerify('####/######/##'),
        ]);
    }
}
