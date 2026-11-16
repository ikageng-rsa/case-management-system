<?php

declare(strict_types=1);

namespace Database\Factories\Client;

use App\Enums\Client\ClientType;
use App\Enums\Client\IdentityType;
use App\Models\Client\Client;
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
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'id_type' => IdentityType::SouthAfricanId,
            'id_number' => $this->faker->unique()->numerify('#############'),
        ];
    }

    /** A client from abroad, known by a passport and a date of birth. */
    public function foreign(): static
    {
        return $this->state(fn (array $attributes) => [
            'id_type' => IdentityType::Passport,
            'id_number' => $this->faker->unique()->bothify('??#######'),
            'date_of_birth' => $this->faker->dateTimeBetween('-70 years', '-20 years'),
        ]);
    }

    public function entity(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ClientType::Entity,
            'first_name' => null,
            'last_name' => null,
            'entity_name' => $this->faker->company(),
            'id_type' => null,
            'id_number' => null,
            'registration_number' => $this->faker->unique()->numerify('####/######/##'),
        ]);
    }
}
