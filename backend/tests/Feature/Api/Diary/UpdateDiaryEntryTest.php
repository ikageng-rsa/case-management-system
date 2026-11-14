<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Diary;

use App\Enums\Auth\Role;
use App\Models\Diary\DiaryEntry;
use App\Models\Matter\Matter;
use App\Models\Matter\MatterAssignment;
use App\Models\Narration\Narration;
use App\Models\User\User;
use App\Notifications\DiaryEntryAssigned;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Api\ApiTestCase;

class UpdateDiaryEntryTest extends ApiTestCase
{
    public function test_editing_needs_a_token(): void
    {
        $entry = DiaryEntry::factory()->create();

        $this->patchJson("/api/v1/matters/{$entry->matter_id}/diary-entries/{$entry->id}")->assertJsonApiError(401);
    }

    public function test_anyone_carrying_the_matter_edits_a_pending_entry(): void
    {
        $user = $this->signInAs(Role::CandidateAttorney);
        $matter = $this->carriedBy($user);
        $entry = DiaryEntry::factory()->for($matter)->create();
        $narration = Narration::factory()->for($matter)->create();

        $this->patchJson("/api/v1/matters/{$matter->id}/diary-entries/{$entry->id}", [
            'body' => 'File the amended plea',
            'due_at' => '2030-02-01T09:00:00+00:00',
            'assigned_to' => $entry->assigned_to,
            'narration_id' => $narration->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.id', (string) $entry->id)
            ->assertJsonPath('data.attributes.body', 'File the amended plea')
            ->assertJsonPath('data.attributes.due_at', '2030-02-01T09:00:00+00:00')
            ->assertJsonPath('data.attributes.narration_id', $narration->id);

        $entry->refresh();

        $this->assertSame('File the amended plea', $entry->body);
        $this->assertTrue($entry->narration->is($narration));
    }

    public function test_handing_an_entry_to_a_colleague_tells_them(): void
    {
        Notification::fake();
        $user = $this->signInAs(Role::CandidateAttorney);
        $matter = $this->carriedBy($user);
        $entry = DiaryEntry::factory()->for($matter)->for($user, 'assignee')->create();
        $colleague = User::factory()->create();

        $this->patchJson("/api/v1/matters/{$matter->id}/diary-entries/{$entry->id}", $this->details($entry, ['assigned_to' => $colleague->id]))
            ->assertOk()
            ->assertJsonPath('data.relationships.assignee.data.id', $colleague->id);

        Notification::assertSentTo($colleague, DiaryEntryAssigned::class);
    }

    public function test_a_linked_narration_can_be_removed(): void
    {
        $user = $this->signInAs(Role::CandidateAttorney);
        $matter = $this->carriedBy($user);
        $entry = DiaryEntry::factory()->for($matter)->create();
        $entry->forceFill(['narration_id' => Narration::factory()->for($matter)->create()->id])->save();

        $this->patchJson("/api/v1/matters/{$matter->id}/diary-entries/{$entry->id}", $this->details($entry, ['narration_id' => null]))
            ->assertOk()
            ->assertJsonPath('data.attributes.narration_id', null);
    }

    public function test_every_detail_is_sent_and_checked(): void
    {
        $user = $this->signInAs(Role::CandidateAttorney);
        $matter = $this->carriedBy($user);
        $entry = DiaryEntry::factory()->for($matter)->create();
        $leaver = User::factory()->create();
        $leaver->delete();

        $this->patchJson("/api/v1/matters/{$matter->id}/diary-entries/{$entry->id}", [])
            ->assertJsonApiError(422, '/body')
            ->assertJsonApiError(422, '/due_at')
            ->assertJsonApiError(422, '/assigned_to')
            ->assertJsonApiError(422, '/narration_id');

        $this->patchJson("/api/v1/matters/{$matter->id}/diary-entries/{$entry->id}", $this->details($entry, [
            'assigned_to' => $leaver->id,
            'narration_id' => Narration::factory()->create()->id,
        ]))
            ->assertJsonApiError(422, '/assigned_to')
            ->assertJsonApiError(422, '/narration_id');
    }

    public function test_a_done_entry_stays_as_it_was(): void
    {
        $user = $this->signInAs(Role::CandidateAttorney);
        $matter = $this->carriedBy($user);
        $entry = DiaryEntry::factory()->for($matter)->completed()->create(['body' => 'Filed']);

        $this->patchJson("/api/v1/matters/{$matter->id}/diary-entries/{$entry->id}", $this->details($entry, ['body' => 'Changed']))
            ->assertJsonApiError(409, detail: 'This entry is done, so it can no longer be changed.');

        $this->assertSame('Filed', $entry->fresh()->body);
    }

    public function test_only_those_carrying_an_open_matter_may_edit_its_entries(): void
    {
        $this->signInAs(Role::Secretary);
        $entry = DiaryEntry::factory()->create();

        $this->patchJson("/api/v1/matters/{$entry->matter_id}/diary-entries/{$entry->id}", $this->details($entry))->assertJsonApiError(403);

        $user = $this->signInAs(Role::Attorney);
        $closed = $this->carriedBy($user, Matter::factory()->closed()->create());
        $onClosed = DiaryEntry::factory()->for($closed)->create();

        $this->patchJson("/api/v1/matters/{$closed->id}/diary-entries/{$onClosed->id}", $this->details($onClosed))->assertJsonApiError(403);
    }

    public function test_an_entry_on_another_matter_or_a_matter_the_user_may_not_see_is_not_found(): void
    {
        $user = $this->signInAs(Role::CandidateAttorney);
        $matter = $this->carriedBy($user);
        $elsewhere = DiaryEntry::factory()->create();

        $this->patchJson("/api/v1/matters/{$matter->id}/diary-entries/{$elsewhere->id}", $this->details($elsewhere))->assertJsonApiError(404);
        $this->patchJson("/api/v1/matters/{$elsewhere->matter_id}/diary-entries/{$elsewhere->id}", $this->details($elsewhere))->assertJsonApiError(404);
    }

    /** @return array<string, mixed> */
    private function details(DiaryEntry $entry, array $changes = []): array
    {
        return $changes + [
            'body' => $entry->body,
            'due_at' => $entry->due_at->toIso8601String(),
            'assigned_to' => $entry->assigned_to,
            'narration_id' => $entry->narration_id,
        ];
    }

    private function carriedBy(User $user, ?Matter $matter = null): Matter
    {
        $matter ??= Matter::factory()->create();
        MatterAssignment::factory()->for($matter)->for($user)->create();

        return $matter;
    }
}
