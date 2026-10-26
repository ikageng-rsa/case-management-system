<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Matter;

use App\Enums\Auth\Role;
use App\Enums\Matter\Assignment;
use App\Models\Client\Client;
use App\Models\Diary\DiaryEntry;
use App\Models\Matter\Matter;
use App\Models\Matter\MatterAssignment;
use App\Models\Reference\Court;
use App\Models\User\User;
use Spatie\Activitylog\Models\Activity;
use Tests\Feature\Api\ApiTestCase;

class MatterTest extends ApiTestCase
{
    public function test_matters_need_a_token(): void
    {
        $this->getJson('/api/v1/matters')->assertJsonApiError(401);
    }

    public function test_those_who_may_not_see_every_matter_see_only_their_own(): void
    {
        $user = $this->signInAs(Role::CandidateAttorney);
        $mine = $this->assign(Matter::factory()->create(), $user, Assignment::Assisting);
        Matter::factory()->create();

        $this->getJson('/api/v1/matters')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'matters')
            ->assertJsonPath('data.0.id', $mine->id)
            ->assertJsonPath('data.0.attributes.reference', $mine->reference)
            ->assertJsonPath('data.0.attributes.status', 'open')
            ->assertJsonPath('data.0.attributes.my_capacities', ['assisting'])
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.counts.open', 1);
    }

    public function test_those_who_may_see_every_matter_see_them_all(): void
    {
        $this->signInAs(Role::Secretary);
        Matter::factory()->count(3)->create();

        $this->getJson('/api/v1/matters')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.attributes.my_capacities', []);
    }

    public function test_a_listed_matter_carries_its_client_masked_and_its_type(): void
    {
        $this->signInAs(Role::Secretary);
        $matter = Matter::factory()->for(Client::factory()->state(['id_number' => '9001015800088']))->create();

        $response = $this->getJson('/api/v1/matters')
            ->assertOk()
            ->assertJsonPath('data.0.relationships.client.data', ['id' => $matter->client_id, 'type' => 'clients'])
            ->assertJsonPath('data.0.relationships.matterType.data.type', 'matter-types')
            ->assertJsonMissingPath('data.0.relationships.assignments');

        $included = collect($response->json('included'))->keyBy('type');

        $this->assertSame('·········0088', $included['clients']['attributes']['identifier_masked']);
        $this->assertSame($matter->matterType->code, $included['matter-types']['attributes']['code']);
        $this->assertStringNotContainsString('9001015800088', $response->getContent());
    }

    public function test_the_list_is_filtered_by_mine_closed_and_type(): void
    {
        $user = $this->signInAs(Role::Secretary);
        $mine = $this->assign(Matter::factory()->create(), $user);
        $closed = Matter::factory()->closed()->create();
        $other = Matter::factory()->create();

        $this->getJson('/api/v1/matters?filter=mine')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $mine->id);
        $this->getJson('/api/v1/matters?filter=closed')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $closed->id);
        $this->getJson('/api/v1/matters?filter=open')->assertJsonCount(2, 'data');
        $this->getJson("/api/v1/matters?matter_type_id={$other->matter_type_id}")->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $other->id);

        $this->getJson('/api/v1/matters')
            ->assertJsonPath('meta.counts.open', 2)
            ->assertJsonPath('meta.counts.mine', 1)
            ->assertJsonPath('meta.counts.closed', 1);
    }

    public function test_the_list_can_be_searched(): void
    {
        $this->signInAs(Role::Secretary);
        Matter::factory()->for(Client::factory()->state(['first_name' => 'Thandi', 'last_name' => 'Zulu']))->create();
        Matter::factory()->for(Client::factory()->state(['first_name' => 'Sipho', 'last_name' => 'Mokoena']))->create();

        $this->getJson('/api/v1/matters?q=zulu')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_the_next_diary_date_comes_from_pending_entries_only(): void
    {
        $this->signInAs(Role::Secretary);
        $matter = Matter::factory()->create();
        $next = DiaryEntry::factory()->for($matter)->dueIn(3)->create();
        DiaryEntry::factory()->for($matter)->dueIn(7)->create();
        DiaryEntry::factory()->for($matter)->completed()->create(['due_at' => now()->addDay()]);

        $this->getJson('/api/v1/matters')
            ->assertJsonPath('data.0.attributes.next_due_at', $next->due_at->toIso8601String());
    }

    public function test_a_wrong_filter_or_sort_is_refused(): void
    {
        $this->signInAs(Role::Secretary);

        $this->getJson('/api/v1/matters?filter=everything')->assertJsonApiError(422, '/filter');
        $this->getJson('/api/v1/matters?sort=title')->assertJsonApiError(422, '/sort');
    }

    public function test_a_matter_is_shown_with_its_court_current_team_and_what_the_user_may_do(): void
    {
        $user = $this->signInAs(Role::Attorney);
        $court = Court::factory()->create(['name' => 'High Court', 'seat' => 'Pretoria']);
        $matter = Matter::factory()->for($court)->create();
        $this->assign($matter, $user, Assignment::Responsible);
        $left = MatterAssignment::factory()->for($matter)->capacity(Assignment::Assisting)->unassigned()->create();

        $response = $this->getJson("/api/v1/matters/{$matter->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $matter->id)
            ->assertJsonPath('data.attributes.title', $matter->title)
            ->assertJsonPath('data.attributes.my_capacities', ['responsible'])
            ->assertJsonPath('data.relationships.court.data.type', 'courts')
            ->assertJsonCount(1, 'data.relationships.assignments.data')
            ->assertJsonPath('data.meta.can', ['work' => true, 'assign' => true, 'view_audit' => true]);

        $included = collect($response->json('included'));
        $assignment = $included->firstWhere('type', 'matter-assignments');

        $this->assertSame('responsible', $assignment['attributes']['capacity']);
        $this->assertSame(['id' => (string) $user->id, 'type' => 'users'], $assignment['relationships']['user']['data']);
        $this->assertSame($user->name, $included->where('type', 'users')->firstWhere('id', (string) $user->id)['attributes']['name']);
        $this->assertSame('High Court, Pretoria', $included->firstWhere('type', 'courts')['attributes']['label']);
        $this->assertNull($included->where('type', 'matter-assignments')->firstWhere('id', (string) $left->id));
    }

    public function test_a_team_member_is_shown_by_name_and_title_only(): void
    {
        $user = $this->signInAs(Role::Secretary);
        $matter = Matter::factory()->create();
        $colleague = User::factory()->create(['email' => 'colleague@example.com']);
        $this->assign($matter, $colleague);

        $response = $this->getJson("/api/v1/matters/{$matter->id}")->assertOk();

        $this->assertStringNotContainsString('colleague@example.com', $response->getContent());
    }

    public function test_a_closed_matter_can_be_read_but_not_worked(): void
    {
        $user = $this->signInAs(Role::Attorney);
        $matter = $this->assign(Matter::factory()->closed()->create(), $user);

        $this->getJson("/api/v1/matters/{$matter->id}")
            ->assertOk()
            ->assertJsonPath('data.attributes.status', 'closed')
            ->assertJsonPath('data.meta.can.work', false);
    }

    public function test_a_matter_seen_but_not_carried_cannot_be_worked(): void
    {
        $this->signInAs(Role::Secretary);
        $matter = Matter::factory()->create();

        $this->getJson("/api/v1/matters/{$matter->id}")
            ->assertOk()
            ->assertJsonPath('data.meta.can', ['work' => false, 'assign' => false, 'view_audit' => false]);
    }

    public function test_a_matter_the_user_is_not_assigned_to_is_not_found(): void
    {
        $this->signInAs(Role::CandidateAttorney);
        $matter = Matter::factory()->create();

        $this->getJson("/api/v1/matters/{$matter->id}")->assertJsonApiError(404);
        $this->getJson('/api/v1/matters/'.fake()->uuid())->assertJsonApiError(404);
    }

    public function test_a_matter_is_named_by_its_id_not_its_reference(): void
    {
        $this->signInAs(Role::Secretary);
        $matter = Matter::factory()->create();

        $this->getJson("/api/v1/matters/{$matter->getRouteKey()}")->assertJsonApiError(404);
    }

    public function test_reading_a_matter_is_recorded_as_from_the_app(): void
    {
        $user = $this->signInAs(Role::Secretary);
        $matter = Matter::factory()->create();

        $this->getJson("/api/v1/matters/{$matter->id}")->assertOk();

        $read = Activity::forSubject($matter)->where('event', 'read')->sole();

        $this->assertTrue($read->causer->is($user));
        $this->assertSame('api', $read->getProperty('via'));
    }

    private function assign(Matter $matter, User $user, Assignment $capacity = Assignment::Responsible): Matter
    {
        MatterAssignment::factory()->for($matter)->for($user)->capacity($capacity)->create();

        return $matter;
    }
}
