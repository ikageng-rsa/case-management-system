<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Audit;

use App\Enums\Auth\Role;
use App\Models\Diary\DiaryEntry;
use App\Models\Matter\Matter;
use App\Models\Matter\MatterAssignment;
use App\Models\User\User;
use Spatie\Activitylog\Models\Activity;
use Tests\Feature\Api\ApiTestCase;

class MatterAuditEntryTest extends ApiTestCase
{
    public function test_the_audit_trail_needs_a_token(): void
    {
        $matter = Matter::factory()->create();

        $this->getJson("/api/v1/matters/{$matter->id}/audit-entries")->assertJsonApiError(401);
    }

    public function test_the_trail_lists_what_was_done_to_the_matter_and_its_records_newest_first(): void
    {
        $user = $this->signInAs(Role::Manager);
        $matter = Matter::factory()->create(['title' => 'Old title']);
        $other = Matter::factory()->create();
        Activity::query()->delete();

        $matter->update(['title' => 'New title']);
        $entry = DiaryEntry::factory()->for($matter)->create();
        DiaryEntry::factory()->for($other)->create();

        $response = $this->getJson("/api/v1/matters/{$matter->id}/audit-entries")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.type', 'audit-entries')
            ->assertJsonPath('data.0.attributes.event', 'created')
            ->assertJsonPath('data.0.attributes.subject_type', 'diary_entry')
            ->assertJsonPath('data.0.attributes.subject_id', (string) $entry->id)
            ->assertJsonPath('data.1.attributes.event', 'updated')
            ->assertJsonPath('data.1.attributes.subject_type', 'matter')
            ->assertJsonPath('data.1.attributes.changes.title', ['old' => 'Old title', 'new' => 'New title'])
            ->assertJsonPath('data.1.attributes.via', 'web')
            ->assertJsonPath('data.1.relationships.causer.data', ['id' => $user->id, 'type' => 'users'])
            ->assertJsonPath('meta.total', 2);

        $this->assertNotEmpty($response->json('data.1.attributes.description'));
    }

    public function test_reads_from_the_app_are_in_the_trail_with_where_they_came_from(): void
    {
        $user = $this->signInAs(Role::Attorney);
        $matter = Matter::factory()->create();
        MatterAssignment::factory()->for($matter)->for($user)->create();
        Activity::query()->delete();

        $this->getJson("/api/v1/matters/{$matter->id}")->assertOk();

        $this->getJson("/api/v1/matters/{$matter->id}/audit-entries")
            ->assertOk()
            ->assertJsonPath('data.0.attributes.event', 'read')
            ->assertJsonPath('data.0.attributes.via', 'api')
            ->assertJsonPath('data.0.attributes.changes', []);
    }

    public function test_the_trail_comes_a_page_at_a_time(): void
    {
        $this->signInAs(Role::Manager);
        $matter = Matter::factory()->create();
        Activity::query()->delete();
        DiaryEntry::factory()->for($matter)->count(30)->create();

        $this->getJson("/api/v1/matters/{$matter->id}/audit-entries?page=2")
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.per_page', 25)
            ->assertJsonPath('meta.total', 30);
    }

    public function test_the_trail_needs_view_audit_log(): void
    {
        $user = $this->signInAs(Role::CandidateAttorney);
        $matter = Matter::factory()->create();
        MatterAssignment::factory()->for($matter)->for($user)->create();

        $this->getJson("/api/v1/matters/{$matter->id}/audit-entries")->assertJsonApiError(403);
    }

    public function test_the_trail_of_a_matter_the_user_may_not_see_is_not_found(): void
    {
        $this->signInAs(Role::Attorney);
        $matter = Matter::factory()->create();

        $this->getJson("/api/v1/matters/{$matter->id}/audit-entries")->assertJsonApiError(404);
    }

    public function test_someone_who_has_left_is_still_named(): void
    {
        $this->signInAs(Role::Manager);
        $leaver = User::factory()->create(['name' => 'Former Clerk']);
        $matter = Matter::factory()->create();
        Activity::query()->delete();
        $this->actingAs($leaver);
        $matter->update(['title' => 'Changed by the clerk']);
        $leaver->delete();
        $this->signInAs(Role::Manager);

        $response = $this->getJson("/api/v1/matters/{$matter->id}/audit-entries")->assertOk();

        $this->assertSame('Former Clerk', collect($response->json('included'))->firstWhere('id', $leaver->id)['attributes']['name']);
    }
}
