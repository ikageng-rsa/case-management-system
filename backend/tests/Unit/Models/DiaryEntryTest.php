<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\DiaryEntry;
use App\Models\Matter;
use App\Models\Narration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiaryEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_is_pending_until_completed(): void
    {
        $entry = DiaryEntry::factory()->create();

        $this->assertTrue($entry->isPending());
        $this->assertFalse($entry->isComplete());
    }

    public function test_it_records_who_completed_it_and_when(): void
    {
        $entry = DiaryEntry::factory()->create();
        $user = User::factory()->create();

        $entry->markComplete($user);

        $this->assertTrue($entry->isComplete());
        $this->assertNotNull($entry->completed_at);
        $this->assertTrue($entry->completedBy->is($user));
    }

    public function test_it_is_overdue_once_the_due_date_passes(): void
    {
        $this->assertTrue(DiaryEntry::factory()->overdue()->create()->isOverdue());
        $this->assertFalse(DiaryEntry::factory()->create()->isOverdue());
    }

    public function test_a_completed_entry_is_never_overdue(): void
    {
        $entry = DiaryEntry::factory()->overdue()->create();

        $entry->markComplete(User::factory()->create());

        $this->assertFalse($entry->isOverdue());
    }

    public function test_it_scopes_overdue_entries_to_incomplete_ones(): void
    {
        DiaryEntry::factory()->overdue()->create();
        DiaryEntry::factory()->overdue()->completed()->create();
        DiaryEntry::factory()->create();

        $this->assertCount(1, DiaryEntry::overdue()->get());
        $this->assertCount(2, DiaryEntry::pending()->get());
        $this->assertCount(1, DiaryEntry::completed()->get());
    }

    public function test_it_finds_entries_falling_due_within_a_window(): void
    {
        $soon = DiaryEntry::factory()->dueIn(3)->create();
        DiaryEntry::factory()->dueIn(30)->create();
        DiaryEntry::factory()->overdue()->create();

        $found = DiaryEntry::dueWithin(7)->get();

        $this->assertCount(1, $found);
        $this->assertTrue($found->first()->is($soon));
    }

    public function test_it_scopes_entries_to_an_assignee(): void
    {
        $user = User::factory()->create();
        DiaryEntry::factory()->create(['assigned_to' => $user->id]);
        DiaryEntry::factory()->create();

        $this->assertCount(1, DiaryEntry::assignedTo($user)->get());
        $this->assertCount(1, $user->diaryEntries);
    }

    public function test_it_belongs_to_a_matter_and_an_assignee(): void
    {
        $entry = DiaryEntry::factory()->create();

        $this->assertInstanceOf(Matter::class, $entry->matter);
        $this->assertInstanceOf(User::class, $entry->assignee);
    }

    public function test_it_may_be_linked_to_the_narration_it_came_from(): void
    {
        $narration = Narration::factory()->create();

        $unlinked = DiaryEntry::factory()->create();
        $linked = DiaryEntry::factory()->create(['narration_id' => $narration->id]);

        $this->assertNull($unlinked->narration);
        $this->assertTrue($linked->narration->is($narration));
    }

    public function test_it_outlives_its_narration(): void
    {
        $narration = Narration::factory()->create();
        $entry = DiaryEntry::factory()->create(['narration_id' => $narration->id]);

        $narration->delete();

        $this->assertNull($entry->fresh()->narration_id);
    }

    public function test_it_is_deleted_with_its_matter(): void
    {
        $matter = Matter::factory()->create();
        DiaryEntry::factory()->for($matter)->create();

        $matter->forceDelete();

        $this->assertSame(0, DiaryEntry::query()->count());
    }
}
