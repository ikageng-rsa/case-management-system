<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Diary;

use App\Enums\Auth\Role;
use App\Models\Diary\DiaryEntry;
use App\Models\Matter\Matter;
use App\Models\Matter\MatterAssignment;
use App\Models\User\User;
use Tests\Feature\Api\ApiTestCase;

class DiaryEntryActionsTest extends ApiTestCase
{
    public function test_the_actions_need_a_token(): void
    {
        $entry = DiaryEntry::factory()->create();

        $this->postJson("/api/v1/matters/{$entry->matter_id}/diary-entries/{$entry->id}/complete")->assertJsonApiError(401);
        $this->patchJson("/api/v1/matters/{$entry->matter_id}/diary-entries/{$entry->id}/reminder")->assertJsonApiError(401);
    }

    public function test_a_due_entry_is_completed_and_who_did_it_recorded(): void
    {
        $user = $this->signInAs(Role::CandidateAttorney);
        $matter = $this->carriedBy($user);
        $entry = DiaryEntry::factory()->for($matter)->overdue()->create();

        $this->postJson("/api/v1/matters/{$matter->id}/diary-entries/{$entry->id}/complete")
            ->assertOk()
            ->assertJsonPath('data.id', (string) $entry->id)
            ->assertJsonPath('data.attributes.status', 'completed')
            ->assertJsonPath('data.attributes.is_overdue', false)
            ->assertJsonPath('data.relationships.completedBy.data.id', $user->id);

        $entry->refresh();

        $this->assertTrue($entry->isComplete());
        $this->assertSame($user->id, $entry->completed_by);
    }

    public function test_an_entry_cannot_be_completed_before_it_is_due(): void
    {
        $user = $this->signInAs(Role::CandidateAttorney);
        $matter = $this->carriedBy($user);
        $entry = DiaryEntry::factory()->for($matter)->create(['due_at' => '2030-01-15 09:00:00']);

        $this->postJson("/api/v1/matters/{$matter->id}/diary-entries/{$entry->id}/complete")
            ->assertJsonApiError(409, detail: 'This entry is not due until 15 Jan 2030 09:00, so it cannot be marked done yet.');

        $this->assertTrue($entry->fresh()->isPending());
    }

    public function test_completing_an_entry_already_done_changes_nothing(): void
    {
        $user = $this->signInAs(Role::CandidateAttorney);
        $matter = $this->carriedBy($user);
        $entry = DiaryEntry::factory()->for($matter)->completed()->create();
        $doneBy = $entry->completed_by;

        $this->postJson("/api/v1/matters/{$matter->id}/diary-entries/{$entry->id}/complete")
            ->assertOk()
            ->assertJsonPath('data.relationships.completedBy.data.id', $doneBy);
    }

    public function test_only_those_carrying_an_open_matter_may_complete_its_entries(): void
    {
        $this->signInAs(Role::Secretary);
        $entry = DiaryEntry::factory()->overdue()->create();

        $this->postJson("/api/v1/matters/{$entry->matter_id}/diary-entries/{$entry->id}/complete")->assertJsonApiError(403);

        $user = $this->signInAs(Role::Attorney);
        $closed = $this->carriedBy($user, Matter::factory()->closed()->create());
        $onClosed = DiaryEntry::factory()->for($closed)->overdue()->create();

        $this->postJson("/api/v1/matters/{$closed->id}/diary-entries/{$onClosed->id}/complete")->assertJsonApiError(403);
        $this->assertTrue($onClosed->fresh()->isPending());
    }

    public function test_an_entry_on_another_matter_or_a_matter_the_user_may_not_see_is_not_found(): void
    {
        $user = $this->signInAs(Role::CandidateAttorney);
        $matter = $this->carriedBy($user);
        $elsewhere = DiaryEntry::factory()->overdue()->create();

        $this->postJson("/api/v1/matters/{$matter->id}/diary-entries/{$elsewhere->id}/complete")->assertJsonApiError(404);
        $this->postJson("/api/v1/matters/{$elsewhere->matter_id}/diary-entries/{$elsewhere->id}/complete")->assertJsonApiError(404);
        $this->patchJson("/api/v1/matters/{$matter->id}/diary-entries/{$elsewhere->id}/reminder", ['remind_before_minutes' => 60])->assertJsonApiError(404);
        $this->assertTrue($elsewhere->fresh()->isPending());
    }

    public function test_the_person_an_entry_is_for_changes_its_reminder_or_turns_it_off(): void
    {
        $user = $this->signInAs(Role::CandidateAttorney);
        $matter = $this->carriedBy($user);
        $entry = DiaryEntry::factory()->for($matter)->for($user, 'assignee')->create(['remind_before_minutes' => 1440]);

        $this->patchJson("/api/v1/matters/{$matter->id}/diary-entries/{$entry->id}/reminder", ['remind_before_minutes' => 30])
            ->assertOk()
            ->assertJsonPath('data.attributes.remind_before_minutes', 30)
            ->assertJsonPath('data.attributes.reminder_label', '30 minutes before');

        $this->patchJson("/api/v1/matters/{$matter->id}/diary-entries/{$entry->id}/reminder", ['remind_before_minutes' => null])
            ->assertOk()
            ->assertJsonPath('data.attributes.remind_before_minutes', null);

        $this->assertNull($entry->fresh()->remind_before_minutes);
    }

    public function test_the_reminder_has_to_be_sent_and_in_range(): void
    {
        $user = $this->signInAs(Role::CandidateAttorney);
        $matter = $this->carriedBy($user);
        $entry = DiaryEntry::factory()->for($matter)->for($user, 'assignee')->create();

        $this->patchJson("/api/v1/matters/{$matter->id}/diary-entries/{$entry->id}/reminder", [])->assertJsonApiError(422, '/remind_before_minutes');
        $this->patchJson("/api/v1/matters/{$matter->id}/diary-entries/{$entry->id}/reminder", ['remind_before_minutes' => 1])->assertJsonApiError(422, '/remind_before_minutes');
    }

    public function test_only_the_person_an_entry_is_for_changes_its_reminder(): void
    {
        $user = $this->signInAs(Role::CandidateAttorney);
        $matter = $this->carriedBy($user);
        $entry = DiaryEntry::factory()->for($matter)->for(User::factory(), 'assignee')->create(['remind_before_minutes' => 1440]);

        $this->patchJson("/api/v1/matters/{$matter->id}/diary-entries/{$entry->id}/reminder", ['remind_before_minutes' => 30])
            ->assertJsonApiError(403, detail: 'Only the person this entry is for can change its reminder.');

        $this->assertSame(1440, $entry->fresh()->remind_before_minutes);
    }

    public function test_a_done_entry_has_no_reminder_to_change(): void
    {
        $user = $this->signInAs(Role::CandidateAttorney);
        $matter = $this->carriedBy($user);
        $entry = DiaryEntry::factory()->for($matter)->for($user, 'assignee')->completed()->create(['remind_before_minutes' => 1440]);

        $this->patchJson("/api/v1/matters/{$matter->id}/diary-entries/{$entry->id}/reminder", ['remind_before_minutes' => 30])
            ->assertJsonApiError(409);

        $this->assertSame(1440, $entry->fresh()->remind_before_minutes);
    }

    private function carriedBy(User $user, ?Matter $matter = null): Matter
    {
        $matter ??= Matter::factory()->create();
        MatterAssignment::factory()->for($matter)->for($user)->create();

        return $matter;
    }
}
