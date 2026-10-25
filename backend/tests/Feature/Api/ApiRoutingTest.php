<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_unknown_api_route_answers_in_json(): void
    {
        $this->get('/api/v1/nothing-here')
            ->assertNotFound()
            ->assertJsonStructure(['message']);
    }

    public function test_a_user_can_hold_api_tokens(): void
    {
        $user = User::factory()->create();

        $token = $user->createToken('Pixel 9');

        $this->assertSame($user->getKey(), $token->accessToken->tokenable_id);
        $this->assertSame('user', $token->accessToken->tokenable_type);
    }
}
