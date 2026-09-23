<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class TwoFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'JBSWY3DPEHPK3PXP';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_it_asks_for_a_code_after_the_password(): void
    {
        $user = $this->userWithTwoFactor();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect('/two-factor-challenge');

        $this->assertGuest();
        $this->get('/two-factor-challenge')->assertOk()->assertSee('Authentication code');
    }

    public function test_it_signs_in_with_a_valid_code(): void
    {
        $user = $this->userWithTwoFactor();

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $this->post('/two-factor-challenge', [
            'code' => (new Google2FA)->getCurrentOtp(self::SECRET),
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_it_rejects_a_wrong_code(): void
    {
        $user = $this->userWithTwoFactor();

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $this->from('/two-factor-challenge')->post('/two-factor-challenge', [
            'code' => '000000',
        ])->assertRedirect('/two-factor-challenge')->assertSessionHasErrors('code');

        $this->assertGuest();
    }

    public function test_it_signs_in_with_a_recovery_code_and_spends_it(): void
    {
        $user = $this->userWithTwoFactor();

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $this->post('/two-factor-challenge', [
            'recovery_code' => 'recovery-one',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
        $this->assertNotContains('recovery-one', $user->fresh()->recoveryCodes());
    }

    public function test_it_turns_two_factor_on_after_the_password_is_confirmed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/user/two-factor-authentication')
            ->assertRedirect('/user/confirm-password');

        $this->get('/user/confirm-password')->assertOk();

        $this->withSession(['auth.password_confirmed_at' => time()])
            ->post('/user/two-factor-authentication')
            ->assertRedirect();

        $this->assertNotNull($user->fresh()->two_factor_secret);
        $this->assertNull($user->fresh()->two_factor_confirmed_at);
    }

    public function test_it_never_writes_the_secret_to_the_activity_log(): void
    {
        $user = $this->userWithTwoFactor();

        $this->assertStringNotContainsString(
            'two_factor',
            Activity::forSubject($user)->pluck('properties')->toJson(),
        );
    }

    private function userWithTwoFactor(): User
    {
        $user = User::factory()->create();

        $user->forceFill([
            'two_factor_secret' => encrypt(self::SECRET),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-one', 'recovery-two'])),
            'two_factor_confirmed_at' => now(),
        ])->save();

        return $user;
    }
}
