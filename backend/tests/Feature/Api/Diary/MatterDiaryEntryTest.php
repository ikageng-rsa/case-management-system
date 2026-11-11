<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Diary;

use App\Enums\Auth\Role;
use App\Models\Diary\DiaryEntry;
use App\Models\Matter\Matter;
use App\Models\Matter\MatterAssignment;
use App\Models\User\NotificationPreference;
use App\Models\User\User;
use App\Notifications\DiaryEntryAssigned;
use App\Services\Diary\ReminderLead;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Api\ApiTestCase;

class MatterDiaryEntryTest extends ApiTestCase
{
    public function test_a_matters_diary_needs_a_token(): void
    {
        $matter = Matter::factory()->create();

        $this->getJson("/api/v1/matters/{$matter->id}/diary-entries")->assertJsonApiError(401);
        $this->postJson("/api/v1/matters/{$matter->id}/diary-entries")->assertJsonApiError(401);
    }

    public function test_pending_entries_are_listed_soonest_first_with_who_they_are_for(): void
    {
        $this->signInAs(Role::Secretary);
        $matter = Matter::factory()->create();
        $assignee = User::factory()->create(['name' => 'Naledi Khumalo']);
        DiaryEntry::factory()->for($matter)->dueIn(5)->create();
        $soonest = DiaryEntry::factory()->for($matter)->for($assignee, 'assignee')->dueIn(1)
            ->create(['body' => 'File the plea', 'remind_before_minutes' => 60]);
        DiaryEntry::factory()->for($matter)->completed()->create();
        DiaryEntry::factory()->create();

        $response = $this->getJson("/api/v1/matters/{$matter->id}/diary-entries")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.type', 'diary-entries')
            ->assertJsonPath('data.0.id', (string) $soonest->id)
            ->assertJsonPath('data.0.attributes.body', 'File the plea')
            ->assertJsonPath('data.0.attributes.status', 'pending')
            ->assertJsonPath('data.0.attributes.is_due', false)
            ->assertJsonPath('data.0.attributes.is_overdue', false)
            ->assertJsonPath('data.0.attributes.remind_before_minutes', 60)
            ->assertJsonPath('data.0.attributes.reminder_label', '1 hour before')
            ->assertJsonPath('data.0.relationships.assignee.data', ['id' => $assignee->id, 'type' => 'users'])
            ->assertJsonMissingPath('data.0.relationships.matter')
            ->assertJsonPath('meta.counts', ['pending' => 2, 'completed' => 1, 'overdue' => 0, 'mine' => 0]);

        $this->assertSame('Naledi Khumalo', collect($response->json('included'))->firstWhere('id', $assignee->id)['attributes']['name']);
    }

    public function test_the_diary_shows_completed_overdue_or_the_users_own(): void
    {
        $user = $this->signInAs(Role::Secretary);
        $matter = Matter::factory()->create();
        $completed = DiaryEntry::factory()->for($matter)->completed()->create();
        $overdue = DiaryEntry::factory()->for($matter)->overdue()->create();
        $mine = DiaryEntry::factory()->for($matter)->for($user, 'assignee')->create();

        $this->getJson("/api/v1/matters/{$matter->id}/diary-entries?show=completed")
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (string) $completed->id)
            ->assertJsonPath('data.0.attributes.status', 'completed')
            ->assertJsonPath('data.0.relationships.completedBy.data.id', $completed->completed_by);

        $this->getJson("/api/v1/matters/{$matter->id}/diary-entries?show=overdue")
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (string) $overdue->id)
            ->assertJsonPath('data.0.attributes.is_overdue', true);

        $this->getJson("/api/v1/matters/{$matter->id}/diary-entries?show=mine")
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (string) $mine->id);

        $this->getJson("/api/v1/matters/{$matter->id}/diary-entries?show=everything")->assertJsonApiError(422, '/show');
    }

    public function test_the_diary_of_a_matter_the_user_may_not_see_is_not_found(): void
    {
        $this->signInAs(Role::CandidateAttorney);
        $matter = Matter::factory()->create();

        $this->getJson("/api/v1/matters/{$matter->id}/diary-entries")->assertJsonApiError(404);
    }

    public function test_an_entry_diarised_for_oneself_follows_their_usual_reminder_and_tells_no_one(): void
    {
        Notification::fake();
        $user = $this->signInAs(Role::CandidateAttorney);
        $matter = $this->carriedBy($user);
        NotificationPreference::factory()->for($user)->create(['type' => 'diary_due_soon', 'lead_minutes' => 120]);

        $response = $this->postJson("/api/v1/matters/{$matter->id}/diary-entries", [
            'body' => 'Serve the summons',
            'due_at' => now()->addDays(3)->toIso8601String(),
        ])
            ->assertCreated()
            ->assertJsonPath('data.type', 'diary-entries')
            ->assertJsonPath('data.attributes.body', 'Serve the summons')
            ->assertJsonPath('data.attributes.remind_before_minutes', 120)
            ->assertJsonPath('data.relationships.assignee.data.id', $user->id);

        $entry = DiaryEntry::query()->findOrFail($response->json('data.id'));

        $this->assertTrue($entry->matter->is($matter));
        Notification::assertNothingSent();
    }

    public function test_an_entry_for_a_colleague_tells_them(): void
    {
        Notification::fake();
        $user = $this->signInAs(Role::CandidateAttorney);
        $matter = $this->carriedBy($user);
        $colleague = User::factory()->create();

        $this->postJson("/api/v1/matters/{$matter->id}/diary-entries", [
            'body' => 'Collect the court order',
            'due_at' => now()->addDay()->toIso8601String(),
            'assigned_to' => $colleague->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.relationships.assignee.data.id', $colleague->id);

        Notification::assertSentTo($colleague, DiaryEntryAssigned::class);
    }

    public function test_a_reminder_is_chosen_in_minutes_or_turned_off(): void
    {
        $user = $this->signInAs(Role::CandidateAttorney);
        $matter = $this->carriedBy($user);
        $entry = ['body' => 'Brief counsel', 'due_at' => now()->addWeek()->toIso8601String()];

        $this->postJson("/api/v1/matters/{$matter->id}/diary-entries", $entry + ['remind_before_minutes' => 90])
            ->assertCreated()
            ->assertJsonPath('data.attributes.remind_before_minutes', 90)
            ->assertJsonPath('data.attributes.reminder_label', '90 minutes before');

        $this->postJson("/api/v1/matters/{$matter->id}/diary-entries", $entry + ['remind_before_minutes' => null])
            ->assertCreated()
            ->assertJsonPath('data.attributes.remind_before_minutes', null)
            ->assertJsonPath('data.attributes.reminder_label', 'No reminder');

        $this->postJson("/api/v1/matters/{$matter->id}/diary-entries", $entry + ['remind_before_minutes' => ReminderLead::MINIMUM - 1])
            ->assertJsonApiError(422, '/remind_before_minutes');

        $this->postJson("/api/v1/matters/{$matter->id}/diary-entries", $entry + ['remind_before_minutes' => ReminderLead::MAXIMUM + 1])
            ->assertJsonApiError(422, '/remind_before_minutes');
    }

    public function test_the_entry_is_checked(): void
    {
        $user = $this->signInAs(Role::CandidateAttorney);
        $matter = $this->carriedBy($user);
        $leaver = User::factory()->create();
        $leaver->delete();

        $this->postJson("/api/v1/matters/{$matter->id}/diary-entries", [])
            ->assertJsonApiError(422, '/body')
            ->assertJsonApiError(422, '/due_at');

        $this->postJson("/api/v1/matters/{$matter->id}/diary-entries", [
            'body' => 'Hand over',
            'due_at' => 'soon',
            'assigned_to' => $leaver->id,
        ])
            ->assertJsonApiError(422, '/due_at')
            ->assertJsonApiError(422, '/assigned_to');
    }

    public function test_only_those_carrying_an_open_matter_may_diarise_on_it(): void
    {
        $this->signInAs(Role::Secretary);
        $seen = Matter::factory()->create();
        $entry = ['body' => 'Follow up', 'due_at' => now()->addDay()->toIso8601String()];

        $this->postJson("/api/v1/matters/{$seen->id}/diary-entries", $entry)->assertJsonApiError(403);

        $user = $this->signInAs(Role::Attorney);
        $closed = $this->carriedBy($user, Matter::factory()->closed()->create());

        $this->postJson("/api/v1/matters/{$closed->id}/diary-entries", $entry)->assertJsonApiError(403);
        $this->assertSame(0, DiaryEntry::query()->count());
    }

    public function test_diarising_on_a_matter_the_user_may_not_see_is_not_found(): void
    {
        $this->signInAs(Role::CandidateAttorney);
        $matter = Matter::factory()->create();

        $this->postJson("/api/v1/matters/{$matter->id}/diary-entries", [
            'body' => 'Follow up',
            'due_at' => now()->addDay()->toIso8601String(),
        ])->assertJsonApiError(404);
    }

    private function carriedBy(User $user, ?Matter $matter = null): Matter
    {
        $matter ??= Matter::factory()->create();
        MatterAssignment::factory()->for($matter)->for($user)->create();

        return $matter;
    }
}
