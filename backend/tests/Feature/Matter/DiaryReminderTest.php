<?php

declare(strict_types=1);

namespace Tests\Feature\Matter;

use App\Enums\Auth\Role as RoleEnum;
use App\Enums\Matter\Assignment;
use App\Models\Diary\DiaryEntry;
use App\Models\Matter\Matter;
use App\Models\Matter\MatterAssignment;
use App\Models\User\NotificationPreference;
use App\Models\User\User;
use App\Notifications\DiaryEntryDueSoon;
use App\Services\Diary\ReminderLead;
use App\Services\Diary\ResolveReminderLead;
use App\Services\Notifications\NotifyUser;
use Database\Seeders\RoleSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class DiaryReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RoleSeeder::class);
    }

    /** @return array{0: User, 1: Matter} someone carrying a matter, and the matter */
    protected function carrier(): array
    {
        $matter = Matter::factory()->create();
        $user = User::factory()->create();
        $user->assignRole(RoleEnum::Attorney->value);
        MatterAssignment::factory()->for($matter)->for($user)->capacity(Assignment::Responsible)->create();

        return [$user, $matter];
    }

    protected function addEntry(User $user, Matter $matter, array $fields = [])
    {
        return $this->actingAs($user)->post(route('matter.diary.store', $matter), $fields + [
            'body' => 'File heads of argument.',
            'due_at' => now()->addDays(10)->toDateTimeString(),
        ]);
    }

    // ---- choosing how early ----

    public function test_each_step_in_the_list_is_stored_in_minutes(): void
    {
        [$user, $matter] = $this->carrier();

        foreach ([30, 60, 1440, 4320, 7200] as $minutes) {
            $this->addEntry($user, $matter, ['remind' => (string) $minutes, 'body' => "Entry {$minutes}"])->assertSessionHasNoErrors();
        }

        $this->assertEqualsCanonicalizing([30, 60, 1440, 4320, 7200], DiaryEntry::pluck('remind_before_minutes')->all());
    }

    public function test_no_reminder_is_a_choice(): void
    {
        [$user, $matter] = $this->carrier();

        $this->addEntry($user, $matter, ['remind' => 'none'])->assertSessionHasNoErrors();

        $this->assertNull(DiaryEntry::firstOrFail()->remind_before_minutes);
    }

    public function test_another_time_is_a_number_and_a_unit(): void
    {
        [$user, $matter] = $this->carrier();

        $this->addEntry($user, $matter, ['remind' => 'custom', 'remind_value' => 2, 'remind_unit' => 'hours', 'body' => 'a'])->assertSessionHasNoErrors();
        $this->addEntry($user, $matter, ['remind' => 'custom', 'remind_value' => 2, 'remind_unit' => 'days', 'body' => 'b'])->assertSessionHasNoErrors();
        $this->addEntry($user, $matter, ['remind' => 'custom', 'remind_value' => 45, 'remind_unit' => 'minutes', 'body' => 'c'])->assertSessionHasNoErrors();

        $this->assertEqualsCanonicalizing([120, 2880, 45], DiaryEntry::pluck('remind_before_minutes')->all());
    }

    public function test_another_time_has_to_be_one_the_reminders_can_keep(): void
    {
        [$user, $matter] = $this->carrier();

        foreach ([
            ['remind' => 'custom', 'remind_value' => 2, 'remind_unit' => 'minutes'],
            ['remind' => 'custom', 'remind_value' => 31, 'remind_unit' => 'days'],
            ['remind' => 'custom', 'remind_unit' => 'hours'],
            ['remind' => 'custom', 'remind_value' => 0, 'remind_unit' => 'hours'],
            ['remind' => 'custom', 'remind_value' => 2, 'remind_unit' => 'fortnights'],
            ['remind' => '12345'],
        ] as $bad) {
            $this->addEntry($user, $matter, $bad)->assertSessionHasErrorsIn('diary');
        }

        $this->assertDatabaseCount('diary_entries', 0);
    }

    public function test_a_form_that_says_nothing_starts_from_the_persons_usual_lead(): void
    {
        [$user, $matter] = $this->carrier();

        $this->assertSame(1440, app(ResolveReminderLead::class)->for($user), 'a day, until they say otherwise');

        NotificationPreference::factory()->for($user)->create(['type' => 'diary_due_soon', 'lead_minutes' => 120]);
        $user->unsetRelation('notificationPreferences');

        $this->addEntry($user, $matter)->assertSessionHasNoErrors();

        $this->assertSame(120, DiaryEntry::firstOrFail()->remind_before_minutes);
    }

    // ---- the personal default ----

    public function test_a_person_sets_their_own_default_with_their_other_notification_choices(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('account.notifications'), ['channels' => ['diary_due_soon' => ['database' => '1', 'mail' => '0']], 'remind' => 'custom', 'remind_value' => 3, 'remind_unit' => 'hours'])
            ->assertSessionHasNoErrors();

        $row = $user->notificationPreferences()->where('type', 'diary_due_soon')->firstOrFail();

        $this->assertSame(180, $row->lead_minutes);
        $this->assertFalse($row->mail);
        $this->assertSame(180, app(ResolveReminderLead::class)->for($user->fresh()));
    }

    public function test_only_the_kind_that_has_a_lead_time_keeps_one(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch(route('account.notifications'), ['channels' => [], 'remind' => '60']);

        $withLead = $user->notificationPreferences()->whereNotNull('lead_minutes')->pluck('type')->all();

        $this->assertSame(['diary_due_soon'], $withLead);
    }

    public function test_saving_the_choices_without_a_lead_keeps_the_one_they_had(): void
    {
        $user = User::factory()->create();
        NotificationPreference::factory()->for($user)->create(['type' => 'diary_due_soon', 'lead_minutes' => 4320]);

        $this->actingAs($user)->patch(route('account.notifications'), ['channels' => []]);

        $this->assertSame(4320, app(ResolveReminderLead::class)->for($user->fresh()));
    }

    // ---- the assignee decides ----

    public function test_an_entry_for_someone_else_carries_the_choice_of_whoever_set_it(): void
    {
        [$author, $matter] = $this->carrier();
        $assignee = User::factory()->create();

        $this->addEntry($author, $matter, ['assigned_to' => $assignee->getKey(), 'remind' => '4320'])->assertSessionHasNoErrors();

        $entry = DiaryEntry::firstOrFail();

        $this->assertSame($assignee->getKey(), $entry->assigned_to);
        $this->assertSame(4320, $entry->remind_before_minutes);
    }

    public function test_the_assignee_can_change_it_for_themselves_and_it_is_logged(): void
    {
        [$author, $matter] = $this->carrier();
        $assignee = User::factory()->create();
        $assignee->assignRole(RoleEnum::Attorney->value);
        MatterAssignment::factory()->for($matter)->for($assignee)->capacity(Assignment::Assisting)->create();

        $entry = DiaryEntry::factory()->for($matter)->create(['assigned_to' => $assignee->getKey(), 'remind_before_minutes' => 4320, 'due_at' => now()->addDays(10)]);
        Activity::query()->delete();

        $this->actingAs($assignee)
            ->patch(route('matter.diary.reminder', [$matter, $entry]), ['remind' => '60'])
            ->assertRedirect(route('matter.show', [$matter, 'tab' => 'diary']))
            ->assertSessionHasNoErrors();

        $this->assertSame(60, $entry->fresh()->remind_before_minutes);

        $logged = Activity::forSubject($entry)->where('event', 'updated')->get();
        $this->assertCount(1, $logged);
        $this->assertTrue($logged->first()->causer->is($assignee));
        $this->assertSame(60, $logged->first()->attribute_changes['attributes']['remind_before_minutes']);
        $this->assertSame(4320, $logged->first()->attribute_changes['old']['remind_before_minutes']);
    }

    public function test_the_assignee_can_turn_it_off(): void
    {
        [$user, $matter] = $this->carrier();
        $entry = DiaryEntry::factory()->for($matter)->create(['assigned_to' => $user->getKey(), 'remind_before_minutes' => 1440]);

        $this->actingAs($user)->patch(route('matter.diary.reminder', [$matter, $entry]), ['remind' => 'none'])->assertSessionHasNoErrors();

        $this->assertNull($entry->fresh()->remind_before_minutes);
    }

    public function test_it_is_theirs_alone_to_change(): void
    {
        [$author, $matter] = $this->carrier();
        $assignee = User::factory()->create();
        $entry = DiaryEntry::factory()->for($matter)->create(['assigned_to' => $assignee->getKey(), 'remind_before_minutes' => 1440]);

        $this->actingAs($author)
            ->patch(route('matter.diary.reminder', [$matter, $entry]), ['remind' => 'none'])
            ->assertForbidden();

        $this->assertSame(1440, $entry->fresh()->remind_before_minutes);
    }

    public function test_an_entry_already_done_keeps_what_it_had(): void
    {
        [$user, $matter] = $this->carrier();
        $entry = DiaryEntry::factory()->for($matter)->overdue()->create(['assigned_to' => $user->getKey(), 'remind_before_minutes' => 60]);
        $entry->markComplete($user);

        $this->actingAs($user)->patch(route('matter.diary.reminder', [$matter, $entry]), ['remind' => 'none']);

        $this->assertSame(60, $entry->fresh()->remind_before_minutes);
    }

    public function test_an_entry_of_another_matter_cannot_be_reached(): void
    {
        [$user, $matter] = $this->carrier();
        $foreign = DiaryEntry::factory()->create(['assigned_to' => $user->getKey()]);

        $this->actingAs($user)->patch(route('matter.diary.reminder', [$matter, $foreign]), ['remind' => 'none'])->assertNotFound();
    }

    // ---- when the moment has already gone ----

    public function test_an_entry_made_inside_its_own_lead_is_not_reminded_at_once(): void
    {
        $inside = DiaryEntry::factory()->create(['due_at' => now()->addHours(2), 'remind_before_minutes' => 1440]);
        $ahead = DiaryEntry::factory()->create(['due_at' => now()->addDays(3), 'remind_before_minutes' => 1440]);

        $this->assertNotNull($inside->reminder_sent_at, 'its moment had already passed, so there is nothing to wait for');
        $this->assertNull($ahead->reminder_sent_at);
    }

    public function test_moving_the_date_or_the_lead_makes_the_reminder_fresh(): void
    {
        $entry = DiaryEntry::factory()->create(['due_at' => now()->addDays(10), 'remind_before_minutes' => 1440]);
        $entry->forceFill(['reminder_sent_at' => now()])->save();

        $entry->update(['due_at' => now()->addDays(12)]);
        $this->assertNull($entry->fresh()->reminder_sent_at);

        $entry->forceFill(['reminder_sent_at' => now()])->save();
        $entry->update(['remind_before_minutes' => 60]);
        $this->assertNull($entry->fresh()->reminder_sent_at);
    }

    public function test_changing_something_else_leaves_it_as_it_was(): void
    {
        $entry = DiaryEntry::factory()->create(['due_at' => now()->addDays(10), 'remind_before_minutes' => 1440]);
        $entry->forceFill(['reminder_sent_at' => now()])->save();

        $entry->update(['body' => 'Reworded.']);

        $this->assertNotNull($entry->fresh()->reminder_sent_at);
    }

    // ---- sending them ----

    public function test_it_reminds_when_the_moment_comes_and_not_before(): void
    {
        Notification::fake();
        $entry = DiaryEntry::factory()->create(['due_at' => now()->addDays(2), 'remind_before_minutes' => 1440]);

        $this->artisan('diary:remind-upcoming')->assertSuccessful();
        Notification::assertNothingSent();

        $this->travel(25)->hours();

        $this->artisan('diary:remind-upcoming')->assertSuccessful();

        Notification::assertSentTo($entry->assignee, DiaryEntryDueSoon::class);
        $this->assertNotNull($entry->fresh()->reminder_sent_at);
    }

    public function test_each_entry_is_reminded_once(): void
    {
        Notification::fake();
        $entry = DiaryEntry::factory()->create(['due_at' => now()->addDays(4), 'remind_before_minutes' => 4320]);
        $this->travel(25)->hours();

        foreach (range(1, 4) as $ignored) {
            $this->artisan('diary:remind-upcoming');
        }

        Notification::assertSentToTimes($entry->assignee, DiaryEntryDueSoon::class, 1);
    }

    public function test_a_half_hour_reminder_is_kept_to_the_minute(): void
    {
        Notification::fake();
        $entry = DiaryEntry::factory()->create(['due_at' => now()->addHours(2), 'remind_before_minutes' => 30]);

        $this->travel(89)->minutes();
        $this->artisan('diary:remind-upcoming');
        Notification::assertNothingSent();

        $this->travel(2)->minutes();
        $this->artisan('diary:remind-upcoming');
        Notification::assertSentTo($entry->assignee, DiaryEntryDueSoon::class);
    }

    public function test_it_reminds_the_assignee_and_not_whoever_set_it(): void
    {
        Notification::fake();
        $matter = Matter::factory()->create();
        $assignee = User::factory()->create();
        $entry = DiaryEntry::factory()->for($matter)->create(['assigned_to' => $assignee->getKey(), 'due_at' => now()->addHours(5), 'remind_before_minutes' => 1440]);
        $entry->forceFill(['reminder_sent_at' => null])->save();

        $this->artisan('diary:remind-upcoming');

        Notification::assertSentTo($assignee, DiaryEntryDueSoon::class);
        Notification::assertCount(1);
    }

    public function test_it_leaves_alone_what_is_done_closed_overdue_or_asked_for_nothing(): void
    {
        Notification::fake();

        $done = DiaryEntry::factory()->create(['due_at' => now()->addDays(2), 'remind_before_minutes' => 4320]);
        $done->forceFill(['reminder_sent_at' => null])->save();
        $done->markComplete(User::factory()->create());

        $closed = DiaryEntry::factory()->for(Matter::factory()->closed())->create(['due_at' => now()->addDays(2), 'remind_before_minutes' => 4320]);

        $overdue = DiaryEntry::factory()->overdue()->create(['remind_before_minutes' => 60]);

        DiaryEntry::factory()->create(['due_at' => now()->addHour(), 'remind_before_minutes' => null]);

        $this->artisan('diary:remind-upcoming')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_an_entry_is_not_marked_when_the_reminder_failed(): void
    {
        $entry = DiaryEntry::factory()->create(['due_at' => now()->addDays(4), 'remind_before_minutes' => 4320]);
        $this->travel(25)->hours();

        $this->mock(NotifyUser::class, fn ($mock) => $mock->shouldReceive('about')->once()->andReturn(false));

        $this->artisan('diary:remind-upcoming')->assertSuccessful();

        $this->assertNull($entry->fresh()->reminder_sent_at, 'it is tried again on the next run');
    }

    public function test_it_goes_where_the_person_chose_and_says_when_it_is_due(): void
    {
        Notification::fake();
        $entry = DiaryEntry::factory()->create(['due_at' => now()->addDays(4), 'remind_before_minutes' => 4320]);
        NotificationPreference::factory()->for($entry->assignee)->create(['type' => 'diary_due_soon', 'database' => true, 'mail' => false]);
        $this->travel(25)->hours();

        $this->artisan('diary:remind-upcoming');

        Notification::assertSentTo($entry->assignee, DiaryEntryDueSoon::class, fn ($n, $channels) => $channels === ['database']);

        $data = (new DiaryEntryDueSoon($entry))->toArray($entry->assignee);
        $this->assertStringContainsString($entry->due_at->format('d M Y H:i'), $data['message']);
        $this->assertSame(route('matter.show', [$entry->matter, 'tab' => 'diary']), $data['url']);
    }

    public function test_it_is_scheduled_every_five_minutes(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event) => str_contains((string) $event->command, 'diary:remind-upcoming'));

        $this->assertNotNull($event);
        $this->assertSame('*/5 * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
    }

    // ---- what is on the screen ----

    public function test_the_new_entry_form_starts_from_the_persons_default(): void
    {
        [$user, $matter] = $this->carrier();
        NotificationPreference::factory()->for($user)->create(['type' => 'diary_due_soon', 'lead_minutes' => 4320]);

        $this->actingAs($user)
            ->get(route('matter.show', [$matter, 'tab' => 'diary']))
            ->assertViewHas('defaultReminder', 4320)
            ->assertSeeInOrder(['x-data="{ choice:', '4320'], false)
            ->assertSee('30 minutes before')
            ->assertSee('5 days before')
            ->assertSee('Another time…');
    }

    public function test_the_diary_shows_how_early_each_is_reminded_and_who_may_change_it(): void
    {
        [$user, $matter] = $this->carrier();
        $mine = DiaryEntry::factory()->for($matter)->create(['assigned_to' => $user->getKey(), 'remind_before_minutes' => 4320, 'body' => 'Mine']);
        $theirs = DiaryEntry::factory()->for($matter)->create(['remind_before_minutes' => 60, 'body' => 'Theirs']);

        $html = $this->actingAs($user)->get(route('matter.show', [$matter, 'tab' => 'diary']))->assertOk()->getContent();

        $this->assertStringContainsString('3 days before', $html);
        $this->assertStringContainsString('1 hour before', $html);
        $this->assertStringContainsString("sheet-open', 'entry-reminder-{$mine->getKey()}'", $html);
        $this->assertStringNotContainsString("entry-reminder-{$theirs->getKey()}", $html);
    }

    public function test_the_lead_is_read_back_as_the_form_would_have_it(): void
    {
        $this->assertSame(['choice' => '1440', 'value' => null, 'unit' => 'hours'], ReminderLead::asChoice(1440));
        $this->assertSame(['choice' => 'none', 'value' => null, 'unit' => 'hours'], ReminderLead::asChoice(null));
        $this->assertSame(['choice' => 'custom', 'value' => 2, 'unit' => 'hours'], ReminderLead::asChoice(120));
        $this->assertSame(['choice' => 'custom', 'value' => 2, 'unit' => 'days'], ReminderLead::asChoice(2880));
        $this->assertSame(['choice' => 'custom', 'value' => 45, 'unit' => 'minutes'], ReminderLead::asChoice(45));

        $this->assertSame('1 day before', ReminderLead::label(1440));
        $this->assertSame('2 hours before', ReminderLead::label(120));
        $this->assertSame('90 minutes before', ReminderLead::label(90));
        $this->assertSame('No reminder', ReminderLead::label(null));
    }
}
