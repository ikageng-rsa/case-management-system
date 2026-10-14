<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_the_sign_in_page_links_to_it(): void
    {
        $this->get(route('login'))->assertSee(route('password.request'), false);
        $this->get(route('password.request'))->assertOk()->assertSee('Forgot your password');
    }

    public function test_a_known_address_is_sent_a_link(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_an_unknown_address_gets_the_same_answer_and_nothing_is_sent(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $known = $this->post(route('password.email'), ['email' => $user->email])->getSession()->get('status');
        $unknown = $this->post(route('password.email'), ['email' => 'nobody@example.com'])
            ->assertSessionHasNoErrors()
            ->getSession()->get('status');

        $this->assertSame($known, $unknown);
        Notification::assertCount(1);
    }

    public function test_the_link_chooses_a_new_password_and_works_once(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))->assertOk();

        $this->post(route('password.update'), [
            'token' => $token, 'email' => $user->email,
            'password' => 'A-new-Passw0rd-here!', 'password_confirmation' => 'A-new-Passw0rd-here!',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('A-new-Passw0rd-here!', $user->fresh()->password));

        $this->post(route('password.update'), [
            'token' => $token, 'email' => $user->email,
            'password' => 'Another-Passw0rd-2!', 'password_confirmation' => 'Another-Passw0rd-2!',
        ])->assertSessionHasErrors('email');
    }

    public function test_a_weak_password_is_refused_and_the_old_one_stays(): void
    {
        $user = User::factory()->create();
        $old = $user->password;

        $this->post(route('password.update'), [
            'token' => Password::createToken($user), 'email' => $user->email,
            'password' => 'short', 'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');

        $this->assertSame($old, $user->fresh()->password);
    }

    public function test_resetting_signs_out_other_sessions_and_is_logged(): void
    {
        config(['session.driver' => 'database']);
        $user = User::factory()->create();
        DB::table('sessions')->insert(['id' => 'elsewhere', 'user_id' => $user->getKey(), 'payload' => '', 'last_activity' => time()]);
        $remember = $user->remember_token;

        $this->post(route('password.update'), [
            'token' => Password::createToken($user), 'email' => $user->email,
            'password' => 'A-new-Passw0rd-here!', 'password_confirmation' => 'A-new-Passw0rd-here!',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('sessions', ['id' => 'elsewhere']);
        $this->assertNotSame($remember, $user->fresh()->remember_token);
        $this->assertTrue(Activity::forSubject($user)->where('event', 'password_reset')->exists());
    }
}
