<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Diary;

use App\Models\DiaryEntry;
use App\Models\Narration;
use App\Models\User;
use App\Services\Diary\CompleteDiaryEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class CompleteDiaryEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_completes_an_entry_without_a_narration(): void
    {
        $user = User::factory()->create();

        $entry = (new CompleteDiaryEntry)->complete(DiaryEntry::factory()->overdue()->create(), $user);

        $this->assertTrue($entry->isComplete());
        $this->assertTrue($entry->completedBy->is($user));
        $this->assertNull($entry->narration_id);
    }

    public function test_it_links_the_narration_that_completed_it(): void
    {
        $entry = DiaryEntry::factory()->overdue()->create();
        $narration = Narration::factory()->for($entry->matter)->create();

        (new CompleteDiaryEntry)->complete($entry, User::factory()->create(), $narration);

        $this->assertTrue($entry->fresh()->narration->is($narration));
    }

    public function test_it_rejects_a_narration_from_another_matter(): void
    {
        $entry = DiaryEntry::factory()->create();

        $this->expectException(InvalidArgumentException::class);

        (new CompleteDiaryEntry)->complete($entry, User::factory()->create(), Narration::factory()->create());
    }

    public function test_completing_again_keeps_the_original_sign_off(): void
    {
        $first = User::factory()->create();
        $entry = DiaryEntry::factory()->create();
        $narration = Narration::factory()->for($entry->matter)->create();

        (new CompleteDiaryEntry)->complete($entry, $first);
        (new CompleteDiaryEntry)->complete($entry, User::factory()->create(), $narration);

        $entry->refresh();
        $this->assertTrue($entry->completedBy->is($first));
        $this->assertNull($entry->narration_id);
    }
}
