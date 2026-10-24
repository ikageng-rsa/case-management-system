<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User\User;
use App\Notifications\SignInCodeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SignInCodeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config(['security.sign_in_code.required' => true]);
        Notification::fake();
    }

    /** The code in the last email sent to the person. */
    protected function emailedCode(User $user): string
    {
        $code = null;

        Notification::assertSentTo($user, SignInCodeNotification::class, function ($notification) use (&$code) {
            $code = $notification->code();

            return true;
        });

        return $code;
    }

    protected function passPassword(User $user, bool $remember = false): void
    {
        $this->post('/login', ['email' => $user->email, 'password' => 'password'] + ($remember ? ['remember' => '1'] : []));
    }

    public function test_the_right_password_leads_to_a_code_not_to_being_signed_in(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('sign-in-code'));

        $this->assertGuest();
        $this->assertMatchesRegularExpression('/^\d{6}$/', $this->emailedCode($user));
        $this->get(route('sign-in-code'))->assertOk()->assertSee('Check your email');
    }

    public function test_a_wrong_password_sends_no_code(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');

        Notification::assertNothingSent();
        $this->assertGuest();
    }

    public function test_the_emailed_code_signs_the_person_in(): void
    {
        $user = User::factory()->create();
        $this->passPassword($user);

        $this->post(route('sign-in-code.verify'), ['code' => $this->emailedCode($user)])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_a_code_can_only_be_used_once(): void
    {
        $user = User::factory()->create();
        $this->passPassword($user);
        $code = $this->emailedCode($user);

        $this->post(route('sign-in-code.verify'), ['code' => $code]);
        auth()->logout();

        // Back past the password again, the old code is no use.
        $this->post(route('sign-in-code.verify'), ['code' => $code])->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_a_wrong_code_is_refused_and_leaves_them_signed_out(): void
    {
        $user = User::factory()->create();
        $this->passPassword($user);

        $this->from(route('sign-in-code'))
            ->post(route('sign-in-code.verify'), ['code' => '000000'])
            ->assertRedirect(route('sign-in-code'))
            ->assertSessionHasErrors('code');

        $this->assertGuest();
    }

    public function test_a_code_is_void_after_too_many_wrong_guesses(): void
    {
        // Held below the request throttle (5 a minute), which would otherwise answer first.
        config(['security.sign_in_code.attempts' => 3]);

        $user = User::factory()->create();
        $this->passPassword($user);
        $code = $this->emailedCode($user);

        foreach (range(1, 3) as $ignored) {
            $this->post(route('sign-in-code.verify'), ['code' => $code === '000000' ? '111111' : '000000']);
        }

        // The right code is no good now; a new one has to be asked for.
        $this->post(route('sign-in-code.verify'), ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_a_code_runs_out(): void
    {
        $user = User::factory()->create();
        $this->passPassword($user);
        $code = $this->emailedCode($user);

        $this->travel(11)->minutes();

        $this->post(route('sign-in-code.verify'), ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_a_code_is_not_kept_in_the_clear(): void
    {
        $user = User::factory()->create();
        $this->passPassword($user);
        $code = $this->emailedCode($user);

        $held = Cache::get('sign-in-code:'.$user->getKey());

        $this->assertNotNull($held);
        $this->assertStringNotContainsString($code, json_encode($held));
    }

    public function test_a_new_code_replaces_the_old_one_but_not_too_soon(): void
    {
        $user = User::factory()->create();
        $this->passPassword($user);
        $first = $this->emailedCode($user);

        $this->from(route('sign-in-code'))
            ->post(route('sign-in-code.resend'))
            ->assertSessionHasErrors('code');

        $this->travel(61)->seconds();

        $this->post(route('sign-in-code.resend'))->assertSessionHas('status');

        Notification::assertSentToTimes($user, SignInCodeNotification::class, 2);

        $this->post(route('sign-in-code.verify'), ['code' => $first])->assertSessionHasErrors('code');
    }

    public function test_the_code_page_cannot_be_reached_without_the_password(): void
    {
        $this->get(route('sign-in-code'))->assertRedirect(route('login'));
        $this->post(route('sign-in-code.verify'), ['code' => '123456'])->assertRedirect(route('login'));
        $this->post(route('sign-in-code.resend'))->assertRedirect(route('login'));
    }

    public function test_someone_else_cannot_use_a_code_that_was_emailed_to_another_person(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $this->passPassword($owner);
        $ownersCode = $this->emailedCode($owner);

        auth()->logout();
        $this->flushSession();

        $this->passPassword($other);

        $this->post(route('sign-in-code.verify'), ['code' => $ownersCode])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_remembering_a_person_survives_the_extra_step(): void
    {
        $user = User::factory()->create();
        $this->passPassword($user, remember: true);

        $this->post(route('sign-in-code.verify'), ['code' => $this->emailedCode($user)]);

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->remember_token);
    }

    public function test_where_the_code_is_switched_off_the_password_signs_in(): void
    {
        config(['security.sign_in_code.required' => false]);
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
        Notification::assertNothingSent();
    }

    public function test_a_person_who_turned_it_off_signs_in_with_the_password_alone(): void
    {
        $user = User::factory()->create(['sign_in_code_enabled' => false]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
        Notification::assertNothingSent();
    }

    public function test_one_persons_choice_does_not_change_anothers(): void
    {
        $off = User::factory()->create(['sign_in_code_enabled' => false]);
        $on = User::factory()->create();

        $this->post('/login', ['email' => $on->email, 'password' => 'password'])->assertRedirect(route('sign-in-code'));
        $this->assertGuest();

        $this->flushSession();

        $this->post('/login', ['email' => $off->email, 'password' => 'password'])->assertRedirect('/dashboard');
    }

    public function test_the_firms_switch_overrides_a_persons_choice(): void
    {
        config(['security.sign_in_code.required' => false]);
        $user = User::factory()->create(['sign_in_code_enabled' => true]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/dashboard');
    }

    public function test_the_code_page_names_the_address_in_full(): void
    {
        $user = User::factory()->create(['email' => 'sarah.nel@example.co.za']);
        $this->passPassword($user);

        $this->get(route('sign-in-code'))->assertSee('sarah.nel@example.co.za');
    }
}
