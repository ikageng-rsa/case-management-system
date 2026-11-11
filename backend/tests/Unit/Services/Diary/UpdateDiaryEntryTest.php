<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Diary;

use App\Models\Diary\DiaryEntry;
use App\Models\Narration\Narration;
use App\Models\User\User;
use App\Notifications\DiaryEntryAssigned;
use App\Services\Diary\UpdateDiaryEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class UpdateDiaryEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_removes_a_narration_linked_by_mistake(): void
    {
        $entry = DiaryEntry::factory()->completed()->create();
        $narration = Narration::factory()->for($entry->matter)->create();
        $entry->forceFill(['narration_id' => $narration->id])->save();

        app(UpdateDiaryEntry::class)->update($entry, $entry->body, $entry->due_at, null);

        $entry->refresh();
        $this->assertNull($entry->narration_id);
        $this->assertTrue($entry->isComplete());
    }

    public function test_it_updates_the_details_and_links_a_narration(): void
    {
        $entry = DiaryEntry::factory()->create();
        $narration = Narration::factory()->for($entry->matter)->create();

        app(UpdateDiaryEntry::class)->update($entry, 'File plea', now()->addDays(2), $narration);

        $entry->refresh();
        $this->assertSame('File plea', $entry->body);
        $this->assertTrue($entry->narration->is($narration));
    }

    public function test_moving_the_due_date_sets_the_reminder_and_overdue_notice_going_again(): void
    {
        $entry = DiaryEntry::factory()->create();
        $entry->forceFill(['reminder_sent_at' => now(), 'overdue_notified_at' => now()])->save();

        app(UpdateDiaryEntry::class)->update($entry, $entry->body, now()->addMonth(), null);

        $entry->refresh();
        $this->assertNull($entry->reminder_sent_at);
        $this->assertNull($entry->overdue_notified_at);
    }

    public function test_keeping_the_due_date_leaves_a_sent_reminder_sent(): void
    {
        $entry = DiaryEntry::factory()->create();
        $entry->forceFill(['reminder_sent_at' => now()])->save();

        app(UpdateDiaryEntry::class)->update($entry, 'Reworded', $entry->due_at, null);

        $this->assertNotNull($entry->fresh()->reminder_sent_at);
    }

    public function test_handing_the_entry_to_someone_else_tells_them_unless_they_did_it(): void
    {
        Notification::fake();
        $entry = DiaryEntry::factory()->create();
        $colleague = User::factory()->create();
        $editor = User::factory()->create();

        app(UpdateDiaryEntry::class)->update($entry, $entry->body, $entry->due_at, null, $colleague, $editor);

        $this->assertSame($colleague->id, $entry->fresh()->assigned_to);
        Notification::assertSentTo($colleague, DiaryEntryAssigned::class);

        $self = User::factory()->create();
        app(UpdateDiaryEntry::class)->update($entry, $entry->body, $entry->due_at, null, $self, $self);

        Notification::assertNotSentTo($self, DiaryEntryAssigned::class);
    }

    public function test_keeping_the_same_assignee_tells_no_one(): void
    {
        Notification::fake();
        $entry = DiaryEntry::factory()->create();

        app(UpdateDiaryEntry::class)->update($entry, 'Reworded', $entry->due_at, null, $entry->assignee, User::factory()->create());

        Notification::assertNothingSent();
    }
}
