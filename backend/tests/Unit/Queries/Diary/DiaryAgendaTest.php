<?php

declare(strict_types=1);

namespace Tests\Unit\Queries\Diary;

use App\Models\Diary\DiaryEntry;
use App\Models\User\User;
use App\Queries\Diary\DiaryAgenda;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiaryAgendaTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_counts_what_is_still_to_do_and_what_is_late(): void
    {
        $user = User::factory()->create();
        DiaryEntry::factory()->for($user, 'assignee')->overdue()->create();
        DiaryEntry::factory()->for($user, 'assignee')->dueIn(3)->create();
        DiaryEntry::factory()->for($user, 'assignee')->completed()->create();

        $agenda = (new DiaryAgenda)->for($user);

        $this->assertSame(2, $agenda['awaitingActionCount']);
        $this->assertSame(1, $agenda['overdueCount']);
    }

    public function test_it_lists_what_is_due_today_earliest_first(): void
    {
        $user = User::factory()->create();
        $this->travelTo(today()->setTime(8, 0));
        $afternoon = DiaryEntry::factory()->for($user, 'assignee')->create(['due_at' => today()->setTime(15, 0)]);
        $morning = DiaryEntry::factory()->for($user, 'assignee')->create(['due_at' => today()->setTime(10, 0)]);
        DiaryEntry::factory()->for($user, 'assignee')->dueIn(1)->create();
        DiaryEntry::factory()->for($user, 'assignee')->completed()->create(['due_at' => today()->setTime(11, 0)]);

        $this->assertSame([$morning->id, $afternoon->id], (new DiaryAgenda)->for($user)['diaryToday']->pluck('id')->all());
    }

    public function test_it_is_only_ever_the_users_own_diary(): void
    {
        $user = User::factory()->create();
        DiaryEntry::factory()->overdue()->create();
        DiaryEntry::factory()->create(['due_at' => now()]);

        $agenda = (new DiaryAgenda)->for($user);

        $this->assertSame(0, $agenda['awaitingActionCount']);
        $this->assertSame(0, $agenda['overdueCount']);
        $this->assertCount(0, $agenda['diaryToday']);
    }
}
