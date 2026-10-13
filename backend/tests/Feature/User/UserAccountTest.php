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

    public function test_the_email_for_a_notification_is_on_until_the_person_chooses_otherwise(): void
    {
        $user = User::factory()->create();

        $this->assertTrue($user->wantsEmailFor(NotificationType::DiaryOverdue));

        $this->actingAs($user)
            ->patch(route('account.notifications'), ['email' => ['diary_overdue' => '0']])
            ->assertRedirect(route('account'))
            ->assertSessionHasNoErrors();

        $this->assertFalse($user->fresh()->wantsEmailFor(NotificationType::DiaryOverdue));

        $this->actingAs($user)->patch(route('account.notifications'), ['email' => ['diary_overdue' => '1']]);

        $this->assertTrue($user->fresh()->wantsEmailFor(NotificationType::DiaryOverdue));
    }

    public function test_choosing_notifications_cannot_be_used_to_slip_staff_fields_in(): void
    {
        $user = $this->userWithRole(RoleEnum::Attorney, ['name' => 'Sarah Nel', 'job_title' => 'Candidate']);

        $this->actingAs($user)
            ->patch(route('account.notifications'), [
                'email' => ['diary_overdue' => '1'],
                'name' => 'Hacked', 'email_address' => 'x@example.com', 'job_title' => 'Director', 'mobile' => '0820000000',
                'preferences' => ['anything' => 'goes'],
            ])
            ->assertSessionHasNoErrors();

        $user->refresh();

        $this->assertSame('Sarah Nel', $user->name);
        $this->assertSame('Candidate', $user->job_title);
        $this->assertNull($user->mobile);
        $this->assertArrayNotHasKey('anything', $user->preferences ?? []);
    }

    public function test_an_overdue_alert_is_emailed_only_to_someone_who_wants_it(): void
    {
        $wants = User::factory()->create();
        $declines = User::factory()->create(['preferences' => ['notifications' => ['diary_overdue' => ['mail' => false]]]]);
        $entry = DiaryEntry::factory()->overdue()->create();

        $notification = new DiaryEntryOverdue($entry);

        $this->assertSame(['database', 'mail'], $notification->via($wants));
        $this->assertSame(['database'], $notification->via($declines));
    }

    public function test_the_overdue_email_names_the_entry_and_links_to_the_matter(): void
    {
        $entry = DiaryEntry::factory()->overdue()->create(['body' => 'File heads of argument']);
        $mail = (new DiaryEntryOverdue($entry))->toMail($entry->assignee);

        $this->assertStringContainsString('File heads of argument', implode(' ', $mail->introLines));
        $this->assertSame(route('matter.show', [$entry->matter, 'tab' => 'diary']), $mail->actionUrl);
    }

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

    public function test_the_account_page_says_how_signing_in_works_without_offering_to_change_it(): void
    {
        $this->actingAs($this->userWithRole(RoleEnum::Attorney))
            ->get(route('account'))
            ->assertSee('a six digit code is emailed to')
            ->assertDontSee('authenticator')
            ->assertDontSee('Set up two-factor');
    }

    public function test_the_old_authenticator_app_routes_are_gone(): void
    {
        $this->get('/two-factor-challenge')->assertNotFound();

        $this->actingAs($this->userWithRole(RoleEnum::Attorney))
            ->post('/user/two-factor-authentication')
            ->assertNotFound();
    }
}
