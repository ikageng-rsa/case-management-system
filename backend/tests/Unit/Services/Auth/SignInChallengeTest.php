<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Auth;

use App\Models\User\User;
use App\Services\Auth\SignInChallenge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SignInChallengeTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_challenge_stands_for_the_person_until_it_is_closed(): void
    {
        $user = User::factory()->create();
        $challenges = new SignInChallenge;

        $challenge = $challenges->open($user, 'Pixel 9');

        $this->assertTrue($challenges->user($challenge)->is($user));
        $this->assertSame('Pixel 9', $challenges->close($challenge));
        $this->assertNull($challenges->user($challenge));
        $this->assertNull($challenges->close($challenge));
    }

    public function test_a_challenge_runs_out_with_the_code(): void
    {
        config(['security.sign_in_code.minutes' => 10]);
        $challenges = new SignInChallenge;

        $challenge = $challenges->open(User::factory()->create(), 'Pixel 9');

        $this->travel(11)->minutes();

        $this->assertNull($challenges->user($challenge));
    }

    public function test_renewing_keeps_it_open_as_long_as_a_new_code(): void
    {
        config(['security.sign_in_code.minutes' => 10]);
        $challenges = new SignInChallenge;
        $challenge = $challenges->open(User::factory()->create(), 'Pixel 9');

        $this->travel(8)->minutes();
        $challenges->renew($challenge);
        $this->travel(8)->minutes();

        $this->assertNotNull($challenges->user($challenge));
    }

    public function test_only_a_hash_of_the_challenge_is_kept(): void
    {
        $challenge = (new SignInChallenge)->open(User::factory()->create(), 'Pixel 9');

        $this->assertFalse(Cache::has("api-sign-in:{$challenge}"));
        $this->assertTrue(Cache::has('api-sign-in:'.hash('sha256', $challenge)));
    }

    public function test_a_deactivated_user_no_longer_answers_to_their_challenge(): void
    {
        $user = User::factory()->create();
        $challenges = new SignInChallenge;
        $challenge = $challenges->open($user, 'Pixel 9');

        $user->delete();

        $this->assertNull($challenges->user($challenge));
    }
}
