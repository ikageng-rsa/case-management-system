<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Diary;

use App\Models\DiaryEntry;
use App\Models\Narration;
use App\Services\Diary\UpdateDiaryEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateDiaryEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_removes_a_narration_linked_by_mistake(): void
    {
        $entry = DiaryEntry::factory()->completed()->create();
        $narration = Narration::factory()->for($entry->matter)->create();
        $entry->forceFill(['narration_id' => $narration->id])->save();

        (new UpdateDiaryEntry)->update($entry, $entry->body, $entry->due_at, null);

        $entry->refresh();
        $this->assertNull($entry->narration_id);
        $this->assertTrue($entry->isComplete());
    }

    public function test_it_updates_the_details_and_links_a_narration(): void
    {
        $entry = DiaryEntry::factory()->create();
        $narration = Narration::factory()->for($entry->matter)->create();

        (new UpdateDiaryEntry)->update($entry, 'File plea', now()->addDays(2), $narration);

        $entry->refresh();
        $this->assertSame('File plea', $entry->body);
        $this->assertTrue($entry->narration->is($narration));
    }
}
