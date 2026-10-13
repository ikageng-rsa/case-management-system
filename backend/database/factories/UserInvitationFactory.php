<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\UserInvitation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserInvitation>
 */
class UserInvitationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'roles' => [],
            'token_hash' => UserInvitation::hash(UserInvitation::newToken()),
            'lifetime_days' => 7,
            'expires_at' => now()->addDays(7),
            'sent_at' => now(),
        ];
    }

    /** Create it with a known token, so a test can follow the link. */
    public function withToken(string $token): static
    {
        return $this->state(fn (array $attributes) => ['token_hash' => UserInvitation::hash($token)]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => ['expires_at' => now()->subDay()]);
    }

    public function revoked(): static
    {
        return $this->state(fn (array $attributes) => ['revoked_at' => now()]);
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => ['accepted_at' => now()]);
    }
}
