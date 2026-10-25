<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Auth;

use App\Enums\Auth\Role;
use App\Models\User\User;
use App\Notifications\SignInCodeNotification;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class SignInTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

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

    protected function passPassword(User $user): string
    {
        return $this->postJson('/api/v1/auth/sign-in', [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'Pixel 9',
        ])->assertOk()->json('data.id');
    }

    public function test_the_right_password_gives_a_challenge_and_emails_a_code_but_no_token(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/v1/auth/sign-in', ['email' => $user->email, 'password' => 'password', 'device_name' => 'Pixel 9'])
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.api+json')
            ->assertJsonPath('data.type', 'sign-in-challenges')
            ->assertJsonPath('data.attributes.expires_in', 600)
            ->assertJsonMissingPath('meta.token');

        $this->assertMatchesRegularExpression('/^\d{6}$/', $this->emailedCode($user));
        $this->assertSame(0, PersonalAccessToken::query()->count());
    }

    public function test_the_address_is_matched_whatever_its_case(): void
    {
        $user = User::factory()->create(['email' => 'thandi@example.com']);

        $this->postJson('/api/v1/auth/sign-in', ['email' => 'Thandi@Example.com', 'password' => 'password', 'device_name' => 'Pixel 9'])
            ->assertOk();

        Notification::assertSentTo($user, SignInCodeNotification::class);
    }

    public function test_a_wrong_password_sends_no_code(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/v1/auth/sign-in', ['email' => $user->email, 'password' => 'wrong', 'device_name' => 'Pixel 9'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        Notification::assertNothingSent();
    }

    public function test_an_unknown_address_gets_the_same_answer_as_a_wrong_password(): void
    {
        $this->postJson('/api/v1/auth/sign-in', ['email' => 'nobody@example.com', 'password' => 'password', 'device_name' => 'Pixel 9'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => __('auth.failed')]);
    }

    public function test_a_deactivated_user_cannot_sign_in(): void
    {
        $user = User::factory()->create();
        $user->delete();

        $this->postJson('/api/v1/auth/sign-in', ['email' => $user->email, 'password' => 'password', 'device_name' => 'Pixel 9'])
            ->assertUnprocessable();

        Notification::assertNothingSent();
    }

    public function test_the_code_is_asked_for_even_when_the_person_has_turned_codes_off_on_the_web(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['sign_in_code_enabled' => false])->save();

        $this->passPassword($user);

        Notification::assertSentTo($user, SignInCodeNotification::class);
    }

    public function test_the_right_code_gives_a_token_named_for_the_device(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole(Role::CandidateAttorney->value);
        $challenge = $this->passPassword($user);

        $this->postJson('/api/v1/auth/sign-in/verify', ['challenge_id' => $challenge, 'code' => $this->emailedCode($user)])
            ->assertCreated()
            ->assertJsonPath('data.type', 'users')
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.attributes.roles', [Role::CandidateAttorney->value])
            ->assertJsonPath('data.attributes.permissions', collect(Role::CandidateAttorney->permissions())->pluck('value')->sort()->values()->all())
            ->assertJsonStructure(['meta' => ['token']]);

        $token = PersonalAccessToken::query()->sole();

        $this->assertSame('Pixel 9', $token->name);
        $this->assertTrue($token->tokenable->is($user));
    }

    public function test_a_wrong_code_gives_no_token(): void
    {
        $user = User::factory()->create();
        $challenge = $this->passPassword($user);

        $wrong = $this->emailedCode($user) === '000000' ? '111111' : '000000';

        $this->postJson('/api/v1/auth/sign-in/verify', ['challenge_id' => $challenge, 'code' => $wrong])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');

        $this->assertSame(0, PersonalAccessToken::query()->count());
    }

    public function test_a_challenge_works_only_once(): void
    {
        $user = User::factory()->create();
        $challenge = $this->passPassword($user);
        $code = $this->emailedCode($user);

        $this->postJson('/api/v1/auth/sign-in/verify', ['challenge_id' => $challenge, 'code' => $code])->assertCreated();

        $this->postJson('/api/v1/auth/sign-in/verify', ['challenge_id' => $challenge, 'code' => $code])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('challenge_id');

        $this->assertSame(1, PersonalAccessToken::query()->count());
    }

    public function test_a_made_up_challenge_is_refused(): void
    {
        $this->postJson('/api/v1/auth/sign-in/verify', ['challenge_id' => 'made-up', 'code' => '123456'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('challenge_id');
    }

    public function test_a_new_code_can_be_asked_for_once_a_minute_has_passed(): void
    {
        $user = User::factory()->create();
        $challenge = $this->passPassword($user);

        $this->postJson('/api/v1/auth/sign-in/resend', ['challenge_id' => $challenge])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');

        $this->travel(61)->seconds();

        $this->postJson('/api/v1/auth/sign-in/resend', ['challenge_id' => $challenge])->assertNoContent();

        Notification::assertSentToTimes($user, SignInCodeNotification::class, 2);
    }

    public function test_where_the_firm_has_codes_off_the_password_gives_a_token_at_once(): void
    {
        config(['security.sign_in_code.required' => false]);
        $user = User::factory()->create();

        $this->postJson('/api/v1/auth/sign-in', ['email' => $user->email, 'password' => 'password', 'device_name' => 'Pixel 9'])
            ->assertCreated()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonStructure(['meta' => ['token'], 'data' => ['attributes' => ['permissions']]]);

        Notification::assertNothingSent();
    }

    public function test_guessing_is_throttled(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 5) as $attempt) {
            $this->postJson('/api/v1/auth/sign-in', ['email' => $user->email, 'password' => 'wrong', 'device_name' => 'Pixel 9'])
                ->assertUnprocessable();
        }

        $this->postJson('/api/v1/auth/sign-in', ['email' => $user->email, 'password' => 'password', 'device_name' => 'Pixel 9'])
            ->assertTooManyRequests();
    }
}
