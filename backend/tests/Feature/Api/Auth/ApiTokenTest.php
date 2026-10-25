<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Auth;

use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class ApiTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_token_shows_who_is_signed_in(): void
    {
        $user = User::factory()->create(['job_title' => 'Attorney']);
        $token = $user->createToken('Pixel 9')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.api+json')
            ->assertJsonPath('data.type', 'users')
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.attributes.job_title', 'Attorney')
            ->assertJsonPath('data.attributes.permissions', []);
    }

    public function test_without_a_token_nothing_is_shown(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_a_web_session_does_not_reach_the_api(): void
    {
        $this->actingAs(User::factory()->create());

        $this->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_signing_out_ends_only_this_device(): void
    {
        $user = User::factory()->create();
        $phone = $user->createToken('Pixel 9')->plainTextToken;
        $user->createToken('iPad');

        $this->withToken($phone)->postJson('/api/v1/auth/sign-out')->assertNoContent();

        $this->assertSame(['iPad'], PersonalAccessToken::query()->pluck('name')->all());

        $this->app['auth']->forgetGuards();

        $this->withToken($phone)->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_a_token_left_unused_too_long_stops_working(): void
    {
        config(['security.api_tokens.idle_days' => 30]);
        $token = User::factory()->create()->createToken('Pixel 9')->plainTextToken;

        $this->travel(31)->days();

        $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_a_token_in_use_keeps_working_past_the_idle_limit(): void
    {
        config(['security.api_tokens.idle_days' => 30]);
        $token = User::factory()->create()->createToken('Pixel 9')->plainTextToken;

        $this->travel(20)->days();
        $this->withToken($token)->getJson('/api/v1/me')->assertOk();
        $this->app['auth']->forgetGuards();

        $this->travel(20)->days();
        $this->withToken($token)->getJson('/api/v1/me')->assertOk();
    }

    public function test_a_deactivated_users_token_stops_working(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('Pixel 9')->plainTextToken;

        $user->delete();

        $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
    }
}
