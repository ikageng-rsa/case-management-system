<?php

declare(strict_types=1);

namespace Database\Factories\Matter;

use App\Enums\Matter\PartyRole;
use App\Models\Matter\Matter;
use App\Models\Matter\MatterParty;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MatterParty>
 */
class MatterPartyFactory extends Factory
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
            'name' => fake()->company(),
            'role' => PartyRole::Respondent,
            'representative' => null,
        ];
    }

    public function role(PartyRole $role): static
    {
        return $this->state(fn (array $attributes) => ['role' => $role]);
    }
}
