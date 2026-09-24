<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\DiaryEntry;
use App\Notifications\DiaryEntryOverdue;
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
}
