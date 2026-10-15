<?php

declare(strict_types=1);

namespace Tests\Feature\User;

use App\Enums\Auth\Role as RoleEnum;
use App\Enums\Matter\Assignment;
use App\Models\DiaryEntry;
use App\Models\Matter;
use App\Models\MatterAssignment;
use App\Models\Narration;
use App\Models\User;
use App\Notifications\DiaryEntryAssigned;
use App\Notifications\MatterAssigned;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class DeactivateUserTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RoleSeeder::class);
    }

    protected function director(): User
    {
        $user = User::factory()->create();
        $user->assignRole(RoleEnum::Director->value);

        return $user;
    }

    protected function attorney(): User
    {
        $user = User::factory()->create();
        $user->assignRole(RoleEnum::Attorney->value);

        return $user;
    }

    protected function seat(Matter $matter, User $user, Assignment $capacity): MatterAssignment
    {
        return MatterAssignment::factory()->for($matter)->for($user)->capacity($capacity)->create();
    }

    public function test_someone_with_nothing_open_is_deactivated_with_a_reason(): void
    {
        $director = $this->director();
        $leaver = $this->attorney();

        $this->actingAs($director)
            ->post(route('user.deactivate', $leaver), ['reason' => 'Left the firm.'])
            ->assertRedirect(route('users'))
            ->assertSessionHasNoErrors();

        $this->assertSoftDeleted($leaver);
        $event = Activity::forSubject($leaver)->where('event', 'deactivated')->sole();
        $this->assertSame('Left the firm.', $event->getProperty('reason'));
        $this->assertTrue($event->causer->is($director));
    }

    public function test_a_reason_is_required(): void
    {
        $leaver = $this->attorney();

        $this->actingAs($this->director())
            ->post(route('user.deactivate', $leaver), ['reason' => ''])
            ->assertSessionHasErrorsIn('deactivate_user_'.$leaver->getKey(), 'reason');

        $this->assertNotSoftDeleted($leaver);
    }

    public function test_it_takes_its_own_permission(): void
    {
        $leaver = $this->attorney();

        $this->actingAs($this->attorney())
            ->post(route('user.deactivate', $leaver), ['reason' => 'x'])
            ->assertForbidden();

        $this->assertNotSoftDeleted($leaver);
    }

    public function test_the_manager_can_too(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole(RoleEnum::Manager->value);
        $leaver = $this->attorney();
        $this->director();

        $this->actingAs($manager)->post(route('user.deactivate', $leaver), ['reason' => 'x'])->assertSessionHasNoErrors();

        $this->assertSoftDeleted($leaver);
    }

    public function test_nobody_can_deactivate_themselves(): void
    {
        $director = $this->director();

        $this->actingAs($director)
            ->post(route('user.deactivate', $director), ['reason' => 'x'])
            ->assertSessionHasErrorsIn('deactivate_user_'.$director->getKey(), 'deactivate');

        $this->assertNotSoftDeleted($director);
    }

    public function test_the_last_person_who_can_manage_roles_cannot_be_deactivated(): void
    {
        $only = $this->director();
        $deactivator = User::factory()->create();
        $deactivator->givePermissionTo('deactivate_users');

        $this->actingAs($deactivator)
            ->post(route('user.deactivate', $only), ['reason' => 'x'])
            ->assertSessionHasErrorsIn('deactivate_user_'.$only->getKey(), 'deactivate');

        $this->assertNotSoftDeleted($only);
    }

    public function test_a_responsible_attorney_cannot_go_until_someone_takes_the_matter(): void
    {
        $leaver = $this->attorney();
        $matter = Matter::factory()->create();
        $this->seat($matter, $leaver, Assignment::Responsible);

        $this->actingAs($this->director())
            ->post(route('user.deactivate', $leaver), ['reason' => 'x'])
            ->assertSessionHasErrorsIn('deactivate_user_'.$leaver->getKey(), 'deactivate');

        $this->assertNotSoftDeleted($leaver);
        $this->assertTrue($matter->assignments()->active()->where('user_id', $leaver->getKey())->exists());
    }

    public function test_the_responsible_seat_and_diary_entries_move_to_the_person_chosen(): void
    {
        Notification::fake();
        $leaver = $this->attorney();
        $successor = $this->attorney();
        $matter = Matter::factory()->create();
        $this->seat($matter, $leaver, Assignment::Responsible);
        $entry = DiaryEntry::factory()->for($matter)->create(['assigned_to' => $leaver->getKey(), 'due_at' => now()->addDays(9)]);

        $this->actingAs($this->director())
            ->post(route('user.deactivate', $leaver), ['reason' => 'Resigned', 'successors' => [$matter->getKey() => $successor->getKey()]])
            ->assertSessionHasNoErrors();

        $this->assertSoftDeleted($leaver);
        $this->assertTrue($matter->fresh()->responsibleAttorney()->is($successor));
        $this->assertSame($successor->getKey(), $entry->fresh()->assigned_to);
        $this->assertFalse(MatterAssignment::active()->where('user_id', $leaver->getKey())->exists());

        Notification::assertSentTo($successor, MatterAssigned::class);
        Notification::assertSentTo($successor, DiaryEntryAssigned::class);
    }

    public function test_the_audit_entry_says_what_went_where(): void
    {
        $leaver = $this->attorney();
        $successor = $this->attorney();
        $matter = Matter::factory()->create();
        $this->seat($matter, $leaver, Assignment::Responsible);
        DiaryEntry::factory()->for($matter)->create(['assigned_to' => $leaver->getKey(), 'due_at' => now()->addDays(9)]);

        $this->actingAs($this->director())
            ->post(route('user.deactivate', $leaver), ['reason' => 'x', 'successors' => [$matter->getKey() => $successor->getKey()]]);

        $handed = Activity::forSubject($leaver)->where('event', 'deactivated')->sole()->getProperty('handed_over');

        $this->assertSame([$matter->reference.' to '.$successor->name.' (1 diary entry)'], $handed);
    }

    public function test_an_assisting_member_with_no_entries_is_just_taken_off(): void
    {
        $leaver = $this->attorney();
        $matter = Matter::factory()->create();
        $this->seat($matter, $leaver, Assignment::Assisting);

        $this->actingAs($this->director())
            ->post(route('user.deactivate', $leaver), ['reason' => 'x'])
            ->assertSessionHasNoErrors();

        $this->assertSoftDeleted($leaver);
        $this->assertFalse($matter->assignments()->active()->where('user_id', $leaver->getKey())->exists());
    }

    public function test_an_assisting_member_with_entries_needs_someone_to_take_them(): void
    {
        $leaver = $this->attorney();
        $matter = Matter::factory()->create();
        $this->seat($matter, $leaver, Assignment::Assisting);
        DiaryEntry::factory()->for($matter)->create(['assigned_to' => $leaver->getKey(), 'due_at' => now()->addDays(9)]);

        $this->actingAs($this->director())
            ->post(route('user.deactivate', $leaver), ['reason' => 'x'])
            ->assertSessionHasErrorsIn('deactivate_user_'.$leaver->getKey(), 'deactivate');

        $this->assertNotSoftDeleted($leaver);
    }

    public function test_the_entries_can_go_to_someone_already_on_the_matter(): void
    {
        $leaver = $this->attorney();
        $colleague = $this->attorney();
        $matter = Matter::factory()->create();
        $this->seat($matter, $leaver, Assignment::Assisting);
        $this->seat($matter, $colleague, Assignment::Responsible);
        $entry = DiaryEntry::factory()->for($matter)->create(['assigned_to' => $leaver->getKey(), 'due_at' => now()->addDays(9)]);

        $this->actingAs($this->director())
            ->post(route('user.deactivate', $leaver), ['reason' => 'x', 'successors' => [$matter->getKey() => $colleague->getKey()]])
            ->assertSessionHasNoErrors();

        $this->assertSame($colleague->getKey(), $entry->fresh()->assigned_to);
        $this->assertSame(1, $matter->assignments()->active()->where('user_id', $colleague->getKey())->count(), 'not added twice');
    }

    public function test_or_to_someone_new_who_is_added_to_the_matter(): void
    {
        $leaver = $this->attorney();
        $newcomer = $this->attorney();
        $matter = Matter::factory()->create();
        $this->seat($matter, $leaver, Assignment::Assisting);
        $entry = DiaryEntry::factory()->for($matter)->create(['assigned_to' => $leaver->getKey(), 'due_at' => now()->addDays(9)]);

        $this->actingAs($this->director())
            ->post(route('user.deactivate', $leaver), ['reason' => 'x', 'successors' => [$matter->getKey() => $newcomer->getKey()]])
            ->assertSessionHasNoErrors();

        $this->assertSame($newcomer->getKey(), $entry->fresh()->assigned_to);
        $this->assertTrue($matter->assignments()->active()->inCapacity(Assignment::Assisting)->where('user_id', $newcomer->getKey())->exists());
    }

    public function test_a_deactivated_person_cannot_be_chosen_to_take_over(): void
    {
        $leaver = $this->attorney();
        $gone = $this->attorney();
        $gone->delete();
        $matter = Matter::factory()->create();
        $this->seat($matter, $leaver, Assignment::Responsible);

        $this->actingAs($this->director())
            ->post(route('user.deactivate', $leaver), ['reason' => 'x', 'successors' => [$matter->getKey() => $gone->getKey()]])
            ->assertSessionHasErrorsIn('deactivate_user_'.$leaver->getKey());

        $this->assertNotSoftDeleted($leaver);
    }

    public function test_closed_matters_are_left_as_they_are(): void
    {
        $leaver = $this->attorney();
        $matter = Matter::factory()->closed()->create();
        $this->seat($matter, $leaver, Assignment::Responsible);

        $this->actingAs($this->director())
            ->post(route('user.deactivate', $leaver), ['reason' => 'x'])
            ->assertSessionHasNoErrors();

        $this->assertSoftDeleted($leaver);
        $this->assertTrue($matter->assignments()->active()->where('user_id', $leaver->getKey())->exists(), 'history is not rewritten');
    }

    public function test_they_are_locked_out_and_their_sessions_and_reset_links_go(): void
    {
        $leaver = $this->attorney();
        DB::table('sessions')->insert(['id' => 's1', 'user_id' => $leaver->getKey(), 'payload' => '', 'last_activity' => time()]);
        DB::table('password_reset_tokens')->insert(['email' => $leaver->email, 'token' => 'x', 'created_at' => now()]);

        $this->actingAs($this->director())->post(route('user.deactivate', $leaver), ['reason' => 'x']);

        $this->assertDatabaseMissing('sessions', ['id' => 's1']);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $leaver->email]);

        auth()->logout();
        $this->post(route('login'), ['email' => $leaver->email, 'password' => 'password'])->assertSessionHasErrors();
        $this->assertGuest();
    }

    public function test_the_history_still_names_them(): void
    {
        $leaver = $this->attorney();
        $matter = Matter::factory()->create();
        $narration = Narration::factory()->for($matter)->create(['author_id' => $leaver->getKey()]);
        $done = DiaryEntry::factory()->for($matter)->overdue()->create(['assigned_to' => $leaver->getKey()]);
        $done->markComplete($leaver);

        $this->actingAs($this->director())->post(route('user.deactivate', $leaver), ['reason' => 'x'])->assertSessionHasNoErrors();

        $this->assertSame($leaver->name, $narration->fresh()->author->name);
        $this->assertSame($leaver->name, $done->fresh()->completedBy->name);
        $this->assertSame($leaver->name, $done->fresh()->assignee->name);
    }

    public function test_the_audit_log_page_still_shows_what_a_deactivated_person_did(): void
    {
        $leaver = $this->attorney();
        $this->actingAs($leaver);
        $matter = Matter::factory()->create();
        $matter->update(['title' => 'Changed by them']);
        $director = $this->director();
        $leaver->delete();

        $this->actingAs($director)->get(route('audit-log'))->assertOk()->assertSee($leaver->name);
    }

    public function test_the_page_lists_them_apart_and_they_can_be_reactivated_with_their_roles(): void
    {
        $director = $this->director();
        $leaver = $this->attorney();
        $this->actingAs($director)->post(route('user.deactivate', $leaver), ['reason' => 'x']);

        $this->get(route('users'))->assertOk()->assertSee('Deactivated')->assertSee(route('user.reactivate', $leaver->getKey()), false);

        $this->post(route('user.reactivate', $leaver->getKey()))->assertRedirect(route('users'));

        $back = User::find($leaver->getKey());
        $this->assertNotNull($back);
        $this->assertTrue($back->hasRole(RoleEnum::Attorney->value));
        $this->assertTrue(Activity::forSubject($back)->where('event', 'reactivated')->exists());
    }

    public function test_only_those_who_may_see_the_controls(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole(RoleEnum::Manager->value);
        $this->attorney();

        $this->actingAs($manager)->get(route('users'))->assertSee('Deactivate');

        $viewer = User::factory()->create();
        $viewer->givePermissionTo('view_users');
        $this->actingAs($viewer)->get(route('users'))->assertDontSee('deactivate-user-');
    }

    public function test_the_sheet_offers_the_team_and_everyone_else_for_each_matter(): void
    {
        $director = $this->director();
        $leaver = $this->attorney();
        $colleague = $this->attorney();
        $outsider = $this->attorney();
        $matter = Matter::factory()->create(['title' => 'Mokoena v Dlamini']);
        $this->seat($matter, $leaver, Assignment::Responsible);
        $this->seat($matter, $colleague, Assignment::Assisting);

        $html = $this->actingAs($director)->get(route('users'))->assertOk()->getContent();

        $this->assertStringContainsString($matter->reference, $html);
        $this->assertStringContainsString('Already on this matter', $html);
        $this->assertStringContainsString('name="successors['.$matter->getKey().']"', $html);
        $this->assertStringContainsString($outsider->name, $html);
    }
}
