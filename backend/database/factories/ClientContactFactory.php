<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Client\ContactKind;
use App\Models\Client;
use App\Models\ClientContact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientContact>
 */
class ClientContactFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'kind' => ContactKind::Email,
            'value' => fake()->unique()->safeEmail(),
            'is_primary' => false,
        ];
    }

    public function mobile(): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => ContactKind::Mobile,
            'value' => fake()->unique()->numerify('0#########'),
        ]);
    }

    public function primary(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_primary' => true,
        ]);
    }
}
