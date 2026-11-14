<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Diary;

use App\Enums\Auth\Role;
use App\Models\Diary\DiaryEntry;
use App\Models\Matter\Matter;
use App\Models\Matter\MatterAssignment;
use App\Models\User\User;
use Tests\Feature\Api\ApiTestCase;

class UserDiaryEntryTest extends ApiTestCase
{
    public function test_my_diary_needs_a_token(): void
    {
        $this->getJson('/api/v1/diary-entries')->assertJsonApiError(401);
    }

    public function test_my_pending_entries_across_matters_come_soonest_first_with_their_matter(): void
    {
        $user = $this->signInAs(Role::CandidateAttorney);
        $first = $this->carriedBy($user);
        $second = $this->carriedBy($user);
        $later = DiaryEntry::factory()->for($first)->for($user, 'assignee')->dueIn(5)->create();
        $sooner = DiaryEntry::factory()->for($second)->for($user, 'assignee')->dueIn(1)->create();
        DiaryEntry::factory()->for($first)->create();
        DiaryEntry::factory()->for($first)->for($user, 'assignee')->completed()->create();

        $response = $this->getJson('/api/v1/diary-entries')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', (string) $sooner->id)
            ->assertJsonPath('data.0.relationships.matter.data', ['id' => $second->id, 'type' => 'matters'])
            ->assertJsonPath('data.1.id', (string) $later->id)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.counts', ['pending' => 2, 'overdue' => 0, 'due_today' => 0, 'completed' => 1]);

        $matter = collect($response->json('included'))->where('type', 'matters')->firstWhere('id', $second->id);

        $this->assertSame($second->reference, $matter['attributes']['reference']);
        $this->assertTrue($matter['meta']['can']['work']);
    }

    public function test_my_diary_shows_overdue_or_completed(): void
    {
        $user = $this->signInAs(Role::CandidateAttorney);
        $matter = $this->carriedBy($user);
        $overdue = DiaryEntry::factory()->for($matter)->for($user, 'assignee')->overdue()->create();
        $done = DiaryEntry::factory()->for($matter)->for($user, 'assignee')->completed()->create();
        DiaryEntry::factory()->for($matter)->for($user, 'assignee')->create();

        $this->getJson('/api/v1/diary-entries?show=overdue')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (string) $overdue->id)
            ->assertJsonPath('meta.counts.overdue', 1);

        $this->getJson('/api/v1/diary-entries?show=completed')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (string) $done->id);
    }

    public function test_my_diary_is_narrowed_to_a_run_of_days(): void
    {
        $this->travelTo('2026-11-11 08:00:00');
        $user = $this->signInAs(Role::CandidateAttorney);
        $matter = $this->carriedBy($user);
        $today = DiaryEntry::factory()->for($matter)->for($user, 'assignee')->create(['due_at' => '2026-11-11 16:00:00']);
        $tomorrow = DiaryEntry::factory()->for($matter)->for($user, 'assignee')->create(['due_at' => '2026-11-12 09:00:00']);
        DiaryEntry::factory()->for($matter)->for($user, 'assignee')->create(['due_at' => '2026-11-20 09:00:00']);

        $this->getJson('/api/v1/diary-entries?from=2026-11-11&to=2026-11-11')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (string) $today->id)
            ->assertJsonPath('meta.counts.due_today', 1);

        $this->getJson('/api/v1/diary-entries?from=2026-11-11&to=2026-11-12')
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.1.id', (string) $tomorrow->id);
    }

    public function test_the_view_and_days_are_checked(): void
    {
        $this->signInAs(Role::CandidateAttorney);

        $this->getJson('/api/v1/diary-entries?show=everything')->assertJsonApiError(422, '/show');
        $this->getJson('/api/v1/diary-entries?from=2026-11-12&to=2026-11-11')->assertJsonApiError(422, '/to');
        $this->getJson('/api/v1/diary-entries?from=soon')->assertJsonApiError(422, '/from');
    }

    public function test_an_entry_left_on_a_matter_i_have_come_off_is_not_shown(): void
    {
        $user = $this->signInAs(Role::CandidateAttorney);
        $left = Matter::factory()->create();
        MatterAssignment::factory()->for($left)->for($user)->unassigned()->create();
        DiaryEntry::factory()->for($left)->for($user, 'assignee')->create();

        $this->getJson('/api/v1/diary-entries')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.counts.pending', 0);
    }

    public function test_those_who_see_every_matter_see_all_their_own_entries(): void
    {
        $user = $this->signInAs(Role::Secretary);
        DiaryEntry::factory()->for(Matter::factory())->for($user, 'assignee')->create();

        $this->getJson('/api/v1/diary-entries')->assertOk()->assertJsonCount(1, 'data');
    }

    private function carriedBy(User $user): Matter
    {
        $matter = Matter::factory()->create();
        MatterAssignment::factory()->for($matter)->for($user)->create();

        return $matter;
    }
}
