<?php

declare(strict_types=1);

namespace Tests\Feature\User;

use App\Enums\Auth\Role as RoleEnum;
use App\Enums\Notification\NotificationType;
use App\Models\DiaryEntry;
use App\Models\User;
use App\Notifications\DiaryEntryOverdue;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class UserAccountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RoleSeeder::class);
    }

    protected function userWithRole(RoleEnum $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole($role->value);

        return $user;
    }

    // ---- the staff record belongs to the firm ----

    public function test_a_person_has_no_way_to_change_their_own_staff_record(): void
    {
        $user = $this->userWithRole(RoleEnum::Attorney, ['name' => 'Sarah Nel', 'email' => 'sarah@example.com']);

        $this->actingAs($user)
            ->put('/user/profile-information', ['name' => 'Someone Else', 'email' => 'other@example.com'])
            ->assertStatus(404);

        $user->refresh();

        $this->assertSame('Sarah Nel', $user->name);
        $this->assertSame('sarah@example.com', $user->email);
    }

    public function test_the_account_page_shows_the_record_without_a_way_to_edit_it(): void
    {
        $user = $this->userWithRole(RoleEnum::Attorney, ['name' => 'Sarah Nel', 'email' => 'sarah@example.com', 'job_title' => 'Attorney', 'employee_number' => 'E-104']);
        $user->mobile = '0821234567';
        $user->save();

        $this->actingAs($user)
            ->get(route('account'))
            ->assertOk()
            ->assertSee('Sarah Nel')
            ->assertSee('sarah@example.com')
            ->assertSee('E-104')
            ->assertSee('+27821234567')
            ->assertSee('Attorney')
            ->assertDontSee('name="name"', false)
            ->assertDontSee('name="email"', false)
            ->assertDontSee('name="job_title"', false)
            ->assertSee('Ask a director to correct it');
    }

    // ---- a director keeps it ----

    public function test_a_director_changes_a_persons_staff_record(): void
    {
        $director = $this->userWithRole(RoleEnum::Director);
        $person = $this->userWithRole(RoleEnum::Secretary, ['name' => 'T Baloyi', 'email' => 'tb@example.com']);

        $this->actingAs($director)
            ->patch(route('user.details.update', $person), [
                'name' => 'Thandi Baloyi', 'email' => 'Thandi@Example.com', 'job_title' => 'Legal secretary',
                'mobile' => '082 123 4567', 'office_phone' => '011 555 1234', 'employee_number' => 'E-200',
            ])
            ->assertRedirect(route('users'))
            ->assertSessionHasNoErrors();

        $person->refresh();

        $this->assertSame('Thandi Baloyi', $person->name);
        $this->assertSame('thandi@example.com', $person->email);
        $this->assertSame('Legal secretary', $person->job_title);
        $this->assertSame('+27821234567', $person->mobile);
        $this->assertSame('+27115551234', $person->office_phone);
        $this->assertSame('E-200', $person->employee_number);
    }

    public function test_phone_numbers_are_encrypted_at_rest(): void
    {
        $person = $this->userWithRole(RoleEnum::Secretary);

        $this->actingAs($this->userWithRole(RoleEnum::Director))
            ->patch(route('user.details.update', $person), ['name' => $person->name, 'mobile' => '0821234567']);

        $raw = DB::table('users')->where('id', $person->getKey())->value('mobile');

        $this->assertNotNull($raw);
        $this->assertStringNotContainsString('821234567', $raw);
    }

    public function test_an_empty_email_or_number_keeps_what_is_on_file(): void
    {
        $person = $this->userWithRole(RoleEnum::Secretary, ['email' => 'tb@example.com']);
        $person->mobile = '0821234567';
        $person->save();

        $this->actingAs($this->userWithRole(RoleEnum::Director))
            ->patch(route('user.details.update', $person), ['name' => 'Renamed', 'email' => '', 'mobile' => '', 'office_phone' => ''])
            ->assertSessionHasNoErrors();

        $person->refresh();

        $this->assertSame('Renamed', $person->name);
        $this->assertSame('tb@example.com', $person->email);
        $this->assertSame('+27821234567', $person->mobile);
    }

    public function test_an_email_or_employee_number_cannot_be_taken_from_someone_else(): void
    {
        $director = $this->userWithRole(RoleEnum::Director);
        User::factory()->create(['email' => 'taken@example.com', 'employee_number' => 'E-1']);
        $person = $this->userWithRole(RoleEnum::Secretary);

        $this->actingAs($director)
            ->patch(route('user.details.update', $person), ['name' => 'X', 'email' => 'taken@example.com', 'employee_number' => 'E-1'])
            ->assertSessionHasErrorsIn('user_details_'.$person->getKey(), ['email', 'employee_number']);

        $this->actingAs($director)
            ->patch(route('user.details.update', $person), ['name' => 'X', 'email' => $person->email, 'employee_number' => 'E-2'])
            ->assertSessionHasNoErrors();
    }

    public function test_changing_someones_email_signs_them_out_everywhere_else_but_not_the_director_editing_themselves(): void
    {
        $director = $this->userWithRole(RoleEnum::Director);
        $person = $this->userWithRole(RoleEnum::Secretary);

        foreach ([$person, $director] as $user) {
            DB::table('sessions')->insert(['id' => 'session-'.$user->getKey(), 'user_id' => $user->getKey(), 'payload' => '', 'last_activity' => now()->timestamp]);
        }

        $this->actingAs($director)
            ->patch(route('user.details.update', $person), ['name' => $person->name, 'email' => 'new@example.com']);

        $this->assertDatabaseMissing('sessions', ['user_id' => $person->getKey()]);

        $this->actingAs($director)
            ->patch(route('user.details.update', $director), ['name' => $director->name, 'email' => 'director.new@example.com']);

        $this->assertDatabaseHas('sessions', ['user_id' => $director->getKey()]);
    }

    public function test_changes_are_audited_once_with_numbers_recorded_by_name_only(): void
    {
        $person = $this->userWithRole(RoleEnum::Secretary, ['name' => 'T Baloyi']);
        Activity::query()->delete();

        $this->actingAs($this->userWithRole(RoleEnum::Director))
            ->patch(route('user.details.update', $person), ['name' => 'Thandi Baloyi', 'mobile' => '0821234567']);

        $this->assertSame(1, Activity::forSubject($person)->where('event', 'updated')->count());
        $this->assertSame(1, Activity::forSubject($person)->where('event', 'value_changed')->count());
        $this->assertSame('mobile', Activity::forSubject($person)->where('event', 'value_changed')->firstOrFail()->properties['field']);
        $this->assertStringNotContainsString('821234567', Activity::forSubject($person)->get()->toJson());
    }

    public function test_only_users_who_manage_users_may_change_a_staff_record(): void
    {
        $person = $this->userWithRole(RoleEnum::Attorney);

        $this->actingAs($this->userWithRole(RoleEnum::Secretary))
            ->patch(route('user.details.update', $person), ['name' => 'Renamed'])
            ->assertForbidden();

        $this->assertNotSame('Renamed', $person->fresh()->name);
    }

    public function test_the_users_page_offers_the_record_but_does_not_print_emails_or_numbers_in_full(): void
    {
        $person = $this->userWithRole(RoleEnum::Secretary, ['name' => 'Thandi Baloyi', 'email' => 'thandi.baloyi@example.com', 'job_title' => 'Legal secretary']);
        $person->mobile = '0821234567';
        $person->save();

        $this->actingAs($this->userWithRole(RoleEnum::Director))
            ->get(route('users'))
            ->assertOk()
            ->assertSee('Legal secretary')
            ->assertSee('edit-user-'.$person->getKey())
            ->assertDontSee('thandi.baloyi@example.com')
            ->assertDontSee('0821234567')
            ->assertDontSee('+27821234567');
    }

    // ---- what a person does choose ----

    public function test_the_sidebar_links_a_person_to_their_account(): void
    {
        $this->actingAs($this->userWithRole(RoleEnum::Attorney))
            ->get(route('dashboard'))
            ->assertSee('href="'.route('account').'"', false);
    }

    public function test_the_account_page_is_for_signed_in_users(): void
    {
        $this->get(route('account'))->assertRedirect();
    }

    // ---- what a person does choose: what they are told about ----

    public function test_every_form_on_the_account_page_is_inside_a_sheet(): void
    {
        $html = $this->actingAs($this->userWithRole(RoleEnum::Attorney))->get(route('account'))->assertOk()->getContent();

        $dom = new \DOMDocument;
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);

        $loose = collect($xpath->query("//form[not(ancestor::*[contains(@class, 'offcanvas')])]"))
            ->map(fn ($form) => $form->getAttribute('action'))
            ->reject(fn ($action) => $action === route('logout'))
            ->values()
            ->all();

        $this->assertSame([], $loose, 'a form is sitting on the page itself');

        $inSheets = collect($xpath->query("//form[ancestor::*[contains(@class, 'offcanvas')]]"))->map(fn ($f) => $f->getAttribute('action'))->all();

        $this->assertContains(route('account.notifications'), $inSheets);
        $this->assertContains(route('user-password.update'), $inSheets);
    }

    public function test_the_old_authenticator_app_routes_are_gone(): void
    {
        $this->get('/two-factor-challenge')->assertNotFound();

        $this->actingAs($this->userWithRole(RoleEnum::Attorney))
            ->post('/user/two-factor-authentication')
            ->assertNotFound();
    }

    public function test_each_kind_of_notification_has_its_own_default_channels(): void
    {
        $user = User::factory()->create();

        $this->assertSame(['database', 'mail'], $user->channelsFor(NotificationType::MatterAssigned));
        $this->assertSame(['database'], $user->channelsFor(NotificationType::MatterUnassigned));
        $this->assertSame(['database'], $user->channelsFor(NotificationType::DiaryAssigned));
        $this->assertSame(['database', 'mail'], $user->channelsFor(NotificationType::DiaryOverdue));
    }

    public function test_a_person_chooses_the_system_email_both_or_neither_for_each_kind(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('account.notifications'), ['channels' => [
                'matter_assigned' => ['database' => '1', 'mail' => '0'],
                'matter_unassigned' => ['database' => '0', 'mail' => '1'],
                'diary_assigned' => ['database' => '1', 'mail' => '1'],
                'diary_overdue' => ['database' => '0', 'mail' => '0'],
            ]])
            ->assertRedirect(route('account'))
            ->assertSessionHasNoErrors();

        $user->refresh();

        $this->assertSame(['database'], $user->channelsFor(NotificationType::MatterAssigned));
        $this->assertSame(['mail'], $user->channelsFor(NotificationType::MatterUnassigned));
        $this->assertSame(['database', 'mail'], $user->channelsFor(NotificationType::DiaryAssigned));
        $this->assertSame([], $user->channelsFor(NotificationType::DiaryOverdue));
    }

    public function test_an_unticked_box_is_a_no_and_a_kind_not_mentioned_is_a_no(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('account.notifications'), ['channels' => ['matter_assigned' => ['database' => '1']]])
            ->assertSessionHasNoErrors();

        $user->refresh();

        $this->assertSame(['database'], $user->channelsFor(NotificationType::MatterAssigned));
        $this->assertSame([], $user->channelsFor(NotificationType::DiaryOverdue));
    }

    public function test_choosing_notifications_cannot_be_used_to_slip_staff_fields_in(): void
    {
        $user = $this->userWithRole(RoleEnum::Attorney, ['name' => 'Sarah Nel', 'job_title' => 'Candidate']);

        $this->actingAs($user)
            ->patch(route('account.notifications'), [
                'channels' => ['matter_assigned' => ['database' => '1', 'mail' => '1']],
                'name' => 'Hacked', 'job_title' => 'Director', 'mobile' => '0820000000', 'sign_in_code_enabled' => '0',
                'preferences' => ['anything' => 'goes'],
            ])
            ->assertSessionHasNoErrors();

        $user->refresh();

        $this->assertSame('Sarah Nel', $user->name);
        $this->assertSame('Candidate', $user->job_title);
        $this->assertNull($user->mobile);
        $this->assertTrue($user->usesSignInCode());
        $this->assertArrayNotHasKey('anything', $user->preferences ?? []);
    }

    public function test_an_overdue_alert_goes_where_the_person_chose(): void
    {
        $default = User::factory()->create();
        $systemOnly = User::factory()->create(['preferences' => ['notifications' => ['diary_overdue' => ['database' => true, 'mail' => false]]]]);
        $none = User::factory()->create(['preferences' => ['notifications' => ['diary_overdue' => ['database' => false, 'mail' => false]]]]);
        $notification = new DiaryEntryOverdue(DiaryEntry::factory()->overdue()->create());

        $this->assertSame(['database', 'mail'], $notification->via($default));
        $this->assertSame(['database'], $notification->via($systemOnly));
        $this->assertSame([], $notification->via($none));
    }

    public function test_the_overdue_email_names_the_entry_and_links_to_the_matter(): void
    {
        $entry = DiaryEntry::factory()->overdue()->create(['body' => 'File heads of argument']);
        $mail = (new DiaryEntryOverdue($entry))->toMail($entry->assignee);

        $this->assertStringContainsString('File heads of argument', implode(' ', $mail->introLines));
        $this->assertSame(route('matter.show', [$entry->matter, 'tab' => 'diary']), $mail->actionUrl);
    }

    public function test_the_account_page_shows_where_each_notification_goes(): void
    {
        $user = $this->userWithRole(RoleEnum::Attorney, ['preferences' => ['notifications' => ['diary_overdue' => ['database' => false, 'mail' => false]]]]);

        $this->actingAs($user)
            ->get(route('account'))
            ->assertSee('I am assigned to a matter')
            ->assertSee('Shown in the system and emailed to you')
            ->assertSee('Not sent to you');
    }

    // ---- the sign-in code ----

    public function test_the_sign_in_code_is_on_for_everyone_until_they_turn_it_off(): void
    {
        $this->assertTrue(User::factory()->create()->usesSignInCode());
    }

    public function test_the_account_page_names_the_address_the_code_goes_to_in_full(): void
    {
        $user = $this->userWithRole(RoleEnum::Attorney, ['email' => 'sarah.nel@example.co.za']);

        $this->actingAs($user)
            ->get(route('account'))
            ->assertSee('<strong>sarah.nel@example.co.za</strong>', false);
    }

    public function test_a_person_turns_the_code_off_and_on_with_their_password(): void
    {
        $user = $this->userWithRole(RoleEnum::Attorney, ['password' => bcrypt('a-long-Passw0rd!x')]);

        $this->actingAs($user)
            ->patch(route('account.sign-in-code'), ['enabled' => '0', 'current_password' => 'a-long-Passw0rd!x'])
            ->assertRedirect(route('account'))
            ->assertSessionHasNoErrors();

        $this->assertFalse($user->fresh()->usesSignInCode());

        $this->actingAs($user)->get(route('account'))->assertSee('only thing between this account');

        $this->actingAs($user)
            ->patch(route('account.sign-in-code'), ['enabled' => '1', 'current_password' => 'a-long-Passw0rd!x'])
            ->assertSessionHasNoErrors();

        $this->assertTrue($user->fresh()->usesSignInCode());
    }

    public function test_the_code_cannot_be_turned_off_without_the_password(): void
    {
        $user = $this->userWithRole(RoleEnum::Attorney);

        $this->actingAs($user)
            ->patch(route('account.sign-in-code'), ['enabled' => '0', 'current_password' => 'not-it'])
            ->assertSessionHasErrorsIn('signInCode', 'current_password');

        $this->actingAs($user)
            ->patch(route('account.sign-in-code'), ['enabled' => '0'])
            ->assertSessionHasErrorsIn('signInCode', 'current_password');

        $this->assertTrue($user->fresh()->usesSignInCode());
    }

    public function test_turning_the_code_off_is_audited(): void
    {
        $user = $this->userWithRole(RoleEnum::Attorney, ['password' => bcrypt('a-long-Passw0rd!x')]);
        Activity::query()->delete();

        $this->actingAs($user)->patch(route('account.sign-in-code'), ['enabled' => '0', 'current_password' => 'a-long-Passw0rd!x']);

        $this->assertSame(1, Activity::forSubject($user)->where('event', 'sign_in_code_disabled')->count());
    }

    public function test_every_form_on_the_account_page_is_still_inside_a_sheet(): void
    {
        $html = $this->actingAs($this->userWithRole(RoleEnum::Attorney))->get(route('account'))->assertOk()->getContent();

        $dom = new \DOMDocument;
        @$dom->loadHTML($html);

        $loose = collect((new \DOMXPath($dom))->query("//form[not(ancestor::*[contains(@class, 'offcanvas')])]"))
            ->map(fn ($form) => $form->getAttribute('action'))
            ->reject(fn ($action) => $action === route('logout'))
            ->all();

        $this->assertSame([], array_values($loose));
    }

    public function test_the_sign_in_code_is_a_switch_that_opens_the_dialogue_whichever_way_it_is_set(): void
    {
        $on = $this->userWithRole(RoleEnum::Attorney);

        $this->actingAs($on)->get(route('account'))
            ->assertSee('aria-label="Sign-in code"', false)
            ->assertSee('role="switch"', false)
            ->assertSee("sheet-open', 'sign-in-code'", false)
            ->assertSee('Turn off the sign-in code');

        $off = $this->userWithRole(RoleEnum::Attorney, ['sign_in_code_enabled' => false]);

        $this->actingAs($off)->get(route('account'))
            ->assertSee("sheet-open', 'sign-in-code'", false)
            ->assertSee('Turn on the sign-in code');
    }

    public function test_the_switch_reflects_the_state_it_is_in(): void
    {
        $html = fn (User $user) => $this->actingAs($user)->get(route('account'))->getContent();

        $this->assertMatchesRegularExpression('/role="switch"[^>]*aria-label="Sign-in code"\s*checked/s', $html($this->userWithRole(RoleEnum::Attorney)));
        $this->assertDoesNotMatchRegularExpression('/role="switch"[^>]*aria-label="Sign-in code"\s*checked/s', $html($this->userWithRole(RoleEnum::Attorney, ['sign_in_code_enabled' => false])));
    }
}
