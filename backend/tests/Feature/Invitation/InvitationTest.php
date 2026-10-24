<?php

declare(strict_types=1);

namespace Tests\Feature\Invitation;

use App\Enums\Auth\Role as RoleEnum;
use App\Models\User\Role;
use App\Models\User\User;
use App\Models\UserInvitation;
use App\Notifications\UserInvitationNotification;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class InvitationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RoleSeeder::class);
    }

    protected function userWithRole(RoleEnum $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);

        return $user;
    }

    protected function roleId(RoleEnum $role): int
    {
        return Role::findByName($role->value)->getKey();
    }

    /** The token carried by the link in the email that was sent. */
    protected function sentToken(): string
    {
        $token = null;

        Notification::assertSentOnDemand(UserInvitationNotification::class, function ($notification) use (&$token) {
            $token = basename($notification->url());

            return true;
        });

        return $token;
    }

    // ---- inviting ----

    public function test_an_invitation_is_recorded_and_emailed_with_a_link_that_is_not_stored(): void
    {
        Notification::fake();
        $director = $this->userWithRole(RoleEnum::Director);

        $this->actingAs($director)
            ->post(route('invitation.store'), [
                'name' => 'Sarah Nel',
                'email' => 'S.Nel@Example.com',
                'lifetime_days' => 14,
                'roles' => [$this->roleId(RoleEnum::CandidateAttorney)],
            ])
            ->assertRedirect(route('users'))
            ->assertSessionHasNoErrors();

        $invitation = UserInvitation::firstOrFail();

        $this->assertSame('s.nel@example.com', $invitation->email);
        $this->assertSame([$this->roleId(RoleEnum::CandidateAttorney)], $invitation->roles);
        $this->assertSame(14, $invitation->lifetime_days);
        $this->assertTrue($invitation->expires_at->isSameDay(now()->addDays(14)));
        $this->assertTrue($invitation->inviter->is($director));
        $this->assertNotNull($invitation->sent_at);

        $token = $this->sentToken();

        $this->assertSame(UserInvitation::hash($token), $invitation->token_hash);
        $this->assertStringNotContainsString($token, json_encode(DB_row($invitation)));
        $this->assertStringNotContainsString($token, Activity::query()->get()->toJson());

        Notification::assertSentOnDemand(UserInvitationNotification::class, fn ($n, $channels, $notifiable) => array_key_first($notifiable->routes['mail']) === 's.nel@example.com');
    }

    public function test_the_inviter_chooses_among_the_lifetimes_offered(): void
    {
        Notification::fake();
        $director = $this->userWithRole(RoleEnum::Director);

        $this->actingAs($director)
            ->post(route('invitation.store'), ['name' => 'A', 'email' => 'a@example.com', 'lifetime_days' => 4])
            ->assertSessionHasErrorsIn('invite', 'lifetime_days');

        $this->actingAs($director)
            ->post(route('invitation.store'), ['name' => 'A', 'email' => 'a@example.com'])
            ->assertSessionHasErrorsIn('invite', 'lifetime_days');

        $this->assertDatabaseCount('user_invitations', 0);
        Notification::assertNothingSent();
    }

    public function test_inviting_is_recorded_once_by_the_model(): void
    {
        Notification::fake();
        Activity::query()->delete();

        $this->actingAs($this->userWithRole(RoleEnum::Director))
            ->post(route('invitation.store'), ['name' => 'Sarah Nel', 'email' => 'sarah@example.com', 'lifetime_days' => 7]);

        $this->assertSame(1, Activity::query()->where('log_name', 'user_invitation')->where('event', 'created')->count());
    }

    public function test_choosing_roles_needs_the_assign_roles_permission(): void
    {
        Notification::fake();

        $inviter = User::factory()->create();
        $inviter->givePermissionTo('view_users', 'invite_users');

        $this->actingAs($inviter)
            ->post(route('invitation.store'), [
                'name' => 'Sarah Nel', 'email' => 'sarah@example.com', 'lifetime_days' => 7,
                'roles' => [$this->roleId(RoleEnum::Director)],
            ])
            ->assertSessionHasErrorsIn('invite', 'roles');

        $this->actingAs($inviter)
            ->post(route('invitation.store'), ['name' => 'Sarah Nel', 'email' => 'sarah@example.com', 'lifetime_days' => 7])
            ->assertSessionHasNoErrors();

        $this->assertSame([], UserInvitation::firstOrFail()->roles);
    }

    public function test_an_existing_user_or_a_waiting_invitation_blocks_a_second_one(): void
    {
        Notification::fake();
        $director = $this->userWithRole(RoleEnum::Director);
        User::factory()->create(['email' => 'taken@example.com']);

        $this->actingAs($director)
            ->post(route('invitation.store'), ['name' => 'X', 'email' => 'TAKEN@example.com', 'lifetime_days' => 7])
            ->assertSessionHasErrorsIn('invite', 'email');

        $this->actingAs($director)->post(route('invitation.store'), ['name' => 'Sarah', 'email' => 'sarah@example.com', 'lifetime_days' => 7]);

        $this->actingAs($director)
            ->post(route('invitation.store'), ['name' => 'Sarah', 'email' => 'sarah@example.com', 'lifetime_days' => 7])
            ->assertSessionHasErrorsIn('invite', 'email');

        $this->assertDatabaseCount('user_invitations', 1);
    }

    public function test_an_expired_or_withdrawn_invitation_does_not_block_a_new_one(): void
    {
        Notification::fake();
        UserInvitation::factory()->expired()->create(['email' => 'old@example.com']);
        UserInvitation::factory()->revoked()->create(['email' => 'withdrawn@example.com']);
        $director = $this->userWithRole(RoleEnum::Director);

        foreach (['old@example.com', 'withdrawn@example.com'] as $email) {
            $this->actingAs($director)
                ->post(route('invitation.store'), ['name' => 'Again', 'email' => $email, 'lifetime_days' => 7])
                ->assertSessionHasNoErrors();
        }
    }

    public function test_inviting_needs_invite_users(): void
    {
        Notification::fake();

        $this->actingAs($this->userWithRole(RoleEnum::Secretary))
            ->post(route('invitation.store'), ['name' => 'A', 'email' => 'a@example.com', 'lifetime_days' => 7])
            ->assertForbidden();

        Notification::assertNothingSent();
    }

    // ---- resending and withdrawing ----

    public function test_resending_gives_a_fresh_link_and_kills_the_old_one(): void
    {
        Notification::fake();
        $director = $this->userWithRole(RoleEnum::Director);

        $this->actingAs($director)->post(route('invitation.store'), ['name' => 'Sarah', 'email' => 'sarah@example.com', 'lifetime_days' => 3]);
        $first = $this->sentToken();

        $invitation = UserInvitation::firstOrFail();
        $invitation->forceFill(['expires_at' => now()->addHour()])->save();

        $this->actingAs($director)->post(route('invitation.resend', $invitation))->assertRedirect(route('users'));

        $invitation->refresh();

        $this->assertTrue($invitation->expires_at->isSameDay(now()->addDays(3)), 'the lifetime starts again, as long as it was');
        $this->assertSame(1, Activity::query()->where('event', 'resent')->count());

        auth()->logout();
        $this->get(route('invitation.show', $first))->assertStatus(410);
        $this->assertNotSame(UserInvitation::hash($first), $invitation->token_hash);
    }

    public function test_an_expired_invitation_can_be_sent_again(): void
    {
        Notification::fake();
        $invitation = UserInvitation::factory()->expired()->create();

        $this->actingAs($this->userWithRole(RoleEnum::Director))->post(route('invitation.resend', $invitation));

        $this->assertTrue($invitation->fresh()->isPending());
    }

    public function test_an_accepted_or_withdrawn_invitation_cannot_be_sent_again(): void
    {
        Notification::fake();
        $director = $this->userWithRole(RoleEnum::Director);

        foreach ([UserInvitation::factory()->accepted()->create(), UserInvitation::factory()->revoked()->create()] as $invitation) {
            $this->actingAs($director)
                ->post(route('invitation.resend', $invitation))
                ->assertSessionHasErrorsIn('invitations', 'invitation');
        }

        Notification::assertNothingSent();
    }

    public function test_withdrawing_stops_the_link_and_is_recorded_once(): void
    {
        $invitation = UserInvitation::factory()->withToken('the-token')->create();

        $this->actingAs($this->userWithRole(RoleEnum::Director))
            ->delete(route('invitation.destroy', $invitation))
            ->assertRedirect(route('users'));

        $this->assertTrue($invitation->fresh()->isRevoked());
        $this->assertSame(1, Activity::query()->where('event', 'revoked')->count());

        auth()->logout();
        $this->get(route('invitation.show', 'the-token'))->assertStatus(410);
    }

    public function test_resending_and_withdrawing_need_invite_users(): void
    {
        $invitation = UserInvitation::factory()->create();
        $secretary = $this->userWithRole(RoleEnum::Secretary);

        $this->actingAs($secretary)->post(route('invitation.resend', $invitation))->assertForbidden();
        $this->actingAs($secretary)->delete(route('invitation.destroy', $invitation))->assertForbidden();
    }

    // ---- accepting ----

    public function test_the_link_shows_the_invited_person_their_name_and_email(): void
    {
        UserInvitation::factory()->withToken('the-token')->create(['name' => 'Sarah Nel', 'email' => 'sarah@example.com']);

        $this->get(route('invitation.show', 'the-token'))
            ->assertOk()
            ->assertSee('Welcome, Sarah Nel')
            ->assertSee('sarah@example.com');
    }

    public function test_accepting_creates_the_user_with_their_roles_and_uses_up_the_invitation(): void
    {
        $role = $this->roleId(RoleEnum::Attorney);
        $invitation = UserInvitation::factory()->withToken('the-token')->create([
            'name' => 'Sarah Nel', 'email' => 'sarah@example.com', 'roles' => [$role, 99999],
        ]);

        $this->post(route('invitation.accept', 'the-token'), ['password' => 'a-long-Passw0rd!x', 'password_confirmation' => 'a-long-Passw0rd!x'])
            ->assertRedirect(route('login'))
            ->assertSessionHas('status');

        $user = User::query()->where('email', 'sarah@example.com')->firstOrFail();

        $this->assertSame('Sarah Nel', $user->name);
        $this->assertTrue(Hash::check('a-long-Passw0rd!x', $user->password));
        $this->assertSame(['attorney'], $user->getRoleNames()->all(), 'a role that no longer exists is skipped');
        $this->assertNotNull($user->email_verified_at);

        $this->assertTrue($invitation->fresh()->isAccepted());
        $this->assertSame(1, Activity::query()->where('event', 'accepted')->where('subject_id', $invitation->getKey())->count());
    }

    public function test_an_invitation_can_only_be_used_once(): void
    {
        UserInvitation::factory()->withToken('the-token')->create(['email' => 'sarah@example.com']);
        $password = ['password' => 'a-long-Passw0rd!x', 'password_confirmation' => 'a-long-Passw0rd!x'];

        $this->post(route('invitation.accept', 'the-token'), $password)->assertRedirect(route('login'));
        $this->post(route('invitation.accept', 'the-token'), $password)->assertStatus(410);
        $this->get(route('invitation.show', 'the-token'))->assertStatus(410);

        $this->assertSame(1, User::query()->where('email', 'sarah@example.com')->count());
    }

    public function test_a_weak_or_unconfirmed_password_is_refused_and_the_invitation_survives(): void
    {
        $invitation = UserInvitation::factory()->withToken('the-token')->create();

        $this->post(route('invitation.accept', 'the-token'), ['password' => 'short', 'password_confirmation' => 'short'])
            ->assertSessionHasErrors('password');

        $this->post(route('invitation.accept', 'the-token'), ['password' => 'a-long-Passw0rd!x', 'password_confirmation' => 'different'])
            ->assertSessionHasErrors('password');

        $this->assertTrue($invitation->fresh()->isPending());
        $this->assertDatabaseCount('users', 0);
    }

    public function test_links_that_are_unknown_expired_or_withdrawn_all_look_the_same(): void
    {
        UserInvitation::factory()->withToken('expired')->expired()->create();
        UserInvitation::factory()->withToken('revoked')->revoked()->create();

        foreach (['expired', 'revoked', 'never-existed'] as $token) {
            $this->get(route('invitation.show', $token))
                ->assertStatus(410)
                ->assertSee("This invitation can't be used", false);

            $this->post(route('invitation.accept', $token), ['password' => 'a-long-Passw0rd!x', 'password_confirmation' => 'a-long-Passw0rd!x'])
                ->assertStatus(410);
        }

        $this->assertDatabaseCount('users', 0);
    }

    public function test_an_invitation_for_an_address_that_has_since_become_a_user_is_not_usable(): void
    {
        UserInvitation::factory()->withToken('the-token')->create(['email' => 'sarah@example.com']);
        User::factory()->create(['email' => 'sarah@example.com']);

        $this->post(route('invitation.accept', 'the-token'), ['password' => 'a-long-Passw0rd!x', 'password_confirmation' => 'a-long-Passw0rd!x'])
            ->assertStatus(410);
    }

    public function test_someone_already_signed_in_is_not_shown_the_accept_page(): void
    {
        UserInvitation::factory()->withToken('the-token')->create();

        $this->actingAs($this->userWithRole(RoleEnum::Director))
            ->get(route('invitation.show', 'the-token'))
            ->assertRedirect();
    }

    public function test_the_link_is_throttled(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->get(route('invitation.show', 'guess-'.$i))->assertStatus(410);
        }

        $this->get(route('invitation.show', 'one-more'))->assertStatus(429);
    }

    // ---- the users page ----

    public function test_the_users_page_lists_what_is_waiting_with_the_email_masked(): void
    {
        UserInvitation::factory()->create(['name' => 'Sarah Nel', 'email' => 'sarah.nel@example.com']);
        UserInvitation::factory()->expired()->create(['name' => 'Old Invite', 'email' => 'old@example.com']);
        UserInvitation::factory()->revoked()->create(['name' => 'Withdrawn Person']);
        UserInvitation::factory()->accepted()->create(['name' => 'Joined Already']);

        $this->actingAs($this->userWithRole(RoleEnum::Director))
            ->get(route('users'))
            ->assertOk()
            ->assertSee('Sarah Nel')
            ->assertSee('sa'.str_repeat('·', 7).'@example.com')
            ->assertDontSee('sarah.nel@example.com')
            ->assertSee('Old Invite')
            ->assertSee('Expired')
            ->assertDontSee('Withdrawn Person')
            ->assertDontSee('Joined Already')
            ->assertSee('1 invited');
    }

    public function test_the_invite_form_only_offers_roles_to_those_who_may_give_them(): void
    {
        $inviter = User::factory()->create();
        $inviter->givePermissionTo('view_users', 'invite_users');

        $this->actingAs($inviter)->get(route('users'))
            ->assertSee('Send invitation')
            ->assertDontSee('name="roles[]"', false);

        $this->actingAs($this->userWithRole(RoleEnum::Director))->get(route('users'))
            ->assertSee('name="roles[]"', false);
    }

    public function test_arriving_at_sign_in_after_accepting_shows_the_message(): void
    {
        $this->withSession(['status' => 'Your account is ready.'])
            ->get(route('login'))
            ->assertSee('Your account is ready.');
    }
}

/** What is actually in the table for a row, as the database holds it. */
function DB_row(UserInvitation $invitation): array
{
    return (array) DB::table('user_invitations')->where('id', $invitation->getKey())->first();
}
