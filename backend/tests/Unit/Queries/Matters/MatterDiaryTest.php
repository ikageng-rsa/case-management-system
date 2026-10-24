<?php

declare(strict_types=1);

namespace Tests\Unit\Queries\Matters;

use App\Models\Diary\DiaryEntry;
use App\Models\Matter\Matter;
use App\Models\User;
use App\Queries\Matters\MatterDiary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatterDiaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_the_pending_entries_soonest_due_first(): void
    {
        $matter = Matter::factory()->create();
        $later = DiaryEntry::factory()->for($matter)->dueIn(5)->create();
        $sooner = DiaryEntry::factory()->for($matter)->dueIn(1)->create();
        DiaryEntry::factory()->for($matter)->completed()->create();
        DiaryEntry::factory()->create();

        $diary = (new MatterDiary)->for($matter, User::factory()->create(), 'pending');

        $this->assertSame([$sooner->id, $later->id], $diary['entries']->pluck('id')->all());
    }

    public function test_each_view_lists_only_its_own_entries(): void
    {
        $matter = Matter::factory()->create();
        $user = User::factory()->create();
        $completed = DiaryEntry::factory()->for($matter)->completed()->create();
        $overdue = DiaryEntry::factory()->for($matter)->overdue()->create();
        $mine = DiaryEntry::factory()->for($matter)->for($user, 'assignee')->create();

        $this->assertSame([$completed->id], (new MatterDiary)->for($matter, $user, 'completed')['entries']->pluck('id')->all());
        $this->assertSame([$overdue->id], (new MatterDiary)->for($matter, $user, 'overdue')['entries']->pluck('id')->all());
        $this->assertSame([$mine->id], (new MatterDiary)->for($matter, $user, 'mine')['entries']->pluck('id')->all());
    }

    public function test_it_counts_the_entries_in_each_view(): void
    {
        $matter = Matter::factory()->create();
        $user = User::factory()->create();
        DiaryEntry::factory()->for($matter)->completed()->create();
        DiaryEntry::factory()->for($matter)->overdue()->create();
        DiaryEntry::factory()->for($matter)->for($user, 'assignee')->create();

        $diary = (new MatterDiary)->for($matter, $user, 'pending');

        $this->assertSame(['pending' => 2, 'completed' => 1, 'overdue' => 1, 'mine' => 1], $diary['diaryCounts']);
    }
}
