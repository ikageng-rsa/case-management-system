<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Client\PopiaConsentMethod;
use App\Models\Client;
use App\Models\PopiaConsent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PopiaConsent>
 */
class PopiaConsentFactory extends Factory
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
            'granted' => true,
            'granted_at' => now(),
            'method' => PopiaConsentMethod::Signed,
        ];
    }

    public function withdrawn(): static
    {
        return $this->state(fn (array $attributes) => [
            'withdrawn_at' => now(),
        ]);
    }

    public function refused(): static
    {
        return $this->state(fn (array $attributes) => [
            'granted' => false,
            'granted_at' => null,
        ]);
    }
}
