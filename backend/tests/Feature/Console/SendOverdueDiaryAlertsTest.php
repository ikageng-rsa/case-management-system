<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\DiaryEntry;
use App\Models\Matter\Matter;
use App\Models\NotificationPreference;
use App\Notifications\DiaryEntryOverdue;
use App\Services\Notifications\NotifyUser;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SendOverdueDiaryAlertsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_alerts_assignees_of_overdue_entries_only(): void
    {
        Notification::fake();

        $overdue = DiaryEntry::factory()->overdue()->create();
        DiaryEntry::factory()->dueIn(3)->create();          // not yet due
        DiaryEntry::factory()->overdue()->completed()->create(); // done, never overdue

        $this->artisan('diary:alert-overdue')->assertSuccessful();

        Notification::assertSentTo($overdue->assignee, DiaryEntryOverdue::class);
        Notification::assertCount(1);
    }

    public function test_each_entry_is_reported_once_however_often_it_runs(): void
    {
        Notification::fake();
        $entry = DiaryEntry::factory()->overdue()->create();

        $this->artisan('diary:alert-overdue')->assertSuccessful();
        $this->artisan('diary:alert-overdue')->assertSuccessful();
        $this->artisan('diary:alert-overdue')->assertSuccessful();

        Notification::assertSentToTimes($entry->assignee, DiaryEntryOverdue::class, 1);
        $this->assertNotNull($entry->fresh()->overdue_notified_at);
    }

    public function test_moving_the_date_makes_a_new_deadline_that_is_reported_if_missed(): void
    {
        Notification::fake();
        $entry = DiaryEntry::factory()->overdue()->create();

        $this->artisan('diary:alert-overdue');

        $entry->update(['due_at' => now()->subMinutes(5)]);
        $this->assertNull($entry->fresh()->overdue_notified_at);

        $this->artisan('diary:alert-overdue');

        Notification::assertSentToTimes($entry->assignee, DiaryEntryOverdue::class, 2);
    }

    public function test_changing_something_else_about_the_entry_does_not_make_it_reportable_again(): void
    {
        Notification::fake();
        $entry = DiaryEntry::factory()->overdue()->create();

        $this->artisan('diary:alert-overdue');
        $entry->update(['body' => 'A better description.']);
        $this->artisan('diary:alert-overdue');

        Notification::assertSentToTimes($entry->assignee, DiaryEntryOverdue::class, 1);
    }

    public function test_entries_on_closed_matters_are_left_alone(): void
    {
        Notification::fake();
        $matter = Matter::factory()->closed()->create();
        DiaryEntry::factory()->for($matter)->overdue()->create();

        $this->artisan('diary:alert-overdue')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_an_entry_is_not_marked_told_when_telling_failed(): void
    {
        $entry = DiaryEntry::factory()->overdue()->create();

        $this->mock(NotifyUser::class, fn ($mock) => $mock->shouldReceive('about')->once()->andReturn(false));

        $this->artisan('diary:alert-overdue')->assertSuccessful();

        $this->assertNull($entry->fresh()->overdue_notified_at, 'a failure is tried again on the next run');
    }

    public function test_it_goes_where_the_person_chose(): void
    {
        Notification::fake();
        $entry = DiaryEntry::factory()->overdue()->create();
        NotificationPreference::factory()->for($entry->assignee)->create(['type' => 'diary_overdue', 'database' => true, 'mail' => false]);

        $this->artisan('diary:alert-overdue');

        Notification::assertSentTo($entry->assignee, DiaryEntryOverdue::class, fn ($n, $channels) => $channels === ['database']);
    }

    public function test_it_is_scheduled_to_run_every_hour(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event) => str_contains((string) $event->command, 'diary:alert-overdue'));

        $this->assertNotNull($event, 'the command is on the schedule');
        $this->assertSame('0 * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
    }
}
