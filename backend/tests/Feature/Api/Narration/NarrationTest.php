<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Narration;

use App\Enums\Auth\Role;
use App\Models\Matter\Matter;
use App\Models\Matter\MatterAssignment;
use App\Models\Narration\Narration;
use App\Models\Reference\ActivityType;
use App\Models\User\User;
use Tests\Feature\Api\ApiTestCase;

class NarrationTest extends ApiTestCase
{
    public function test_narrations_need_a_token(): void
    {
        $matter = Matter::factory()->create();

        $this->getJson("/api/v1/matters/{$matter->id}/narrations")->assertJsonApiError(401);
        $this->postJson("/api/v1/matters/{$matter->id}/narrations")->assertJsonApiError(401);
    }

    public function test_the_ledger_is_listed_newest_first_with_its_activity_and_author(): void
    {
        $this->signInAs(Role::Secretary);
        $matter = Matter::factory()->create();
        $type = ActivityType::factory()->create(['code' => 'CALL', 'name' => 'Telephone call']);
        $author = User::factory()->create(['name' => 'Naledi Khumalo']);
        Narration::factory()->for($matter)->occurredAt(now()->subDays(2))->create();
        $latest = Narration::factory()->for($matter)->for($type)->for($author, 'author')->occurredAt(now()->subHour())
            ->create(['body' => 'Called the client', 'quantity' => 15]);
        Narration::factory()->create();

        $response = $this->getJson("/api/v1/matters/{$matter->id}/narrations")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.type', 'narrations')
            ->assertJsonPath('data.0.id', $latest->id)
            ->assertJsonPath('data.0.attributes.body', 'Called the client')
            ->assertJsonPath('data.0.attributes.quantity', 15)
            ->assertJsonPath('data.0.attributes.billable_units', 3)
            ->assertJsonPath('data.0.relationships.activityType.data', ['id' => (string) $type->id, 'type' => 'activity-types'])
            ->assertJsonPath('data.0.relationships.author.data', ['id' => $author->id, 'type' => 'users'])
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.counts.all', 2);

        $included = collect($response->json('included'));

        $this->assertSame('Telephone call', $included->firstWhere('type', 'activity-types')['attributes']['name']);
        $this->assertSame('Naledi Khumalo', $included->firstWhere('id', $author->id)['attributes']['name']);
    }

    public function test_the_ledger_is_filtered_to_the_users_own_or_to_one_activity(): void
    {
        $user = $this->signInAs(Role::Secretary);
        $matter = Matter::factory()->create();
        $call = ActivityType::factory()->create(['code' => 'CALL']);
        $mine = Narration::factory()->for($matter)->for($user, 'author')->create();
        $called = Narration::factory()->for($matter)->for($call)->create();

        $this->getJson("/api/v1/matters/{$matter->id}/narrations?filter=mine")
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mine->id)
            ->assertJsonPath('meta.counts.mine', 1);

        $this->getJson("/api/v1/matters/{$matter->id}/narrations?filter=CALL")
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $called->id)
            ->assertJsonPath('meta.counts.activities.CALL.count', 1);
    }

    public function test_the_ledger_comes_a_page_at_a_time(): void
    {
        $this->signInAs(Role::Secretary);
        $matter = Matter::factory()->create();
        Narration::factory()->for($matter)->count(30)->create();

        $this->getJson("/api/v1/matters/{$matter->id}/narrations?page=2")
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.total', 30);
    }

    public function test_the_ledger_of_a_matter_the_user_may_not_see_is_not_found(): void
    {
        $this->signInAs(Role::CandidateAttorney);
        $matter = Matter::factory()->create();

        $this->getJson("/api/v1/matters/{$matter->id}/narrations")->assertJsonApiError(404);
    }

    public function test_someone_carrying_the_matter_logs_work_on_it(): void
    {
        $user = $this->signInAs(Role::CandidateAttorney);
        $matter = $this->carriedBy($user);
        $type = ActivityType::factory()->create();

        $response = $this->postJson("/api/v1/matters/{$matter->id}/narrations", [
            'activity_type_id' => $type->id,
            'body' => 'Drafted the summons',
            'quantity' => 42,
            'occurred_at' => now()->subHour()->toIso8601String(),
        ])
            ->assertCreated()
            ->assertJsonPath('data.type', 'narrations')
            ->assertJsonPath('data.attributes.body', 'Drafted the summons')
            ->assertJsonPath('data.attributes.quantity', 42)
            ->assertJsonPath('data.relationships.author.data.id', $user->id);

        $narration = Narration::query()->findOrFail($response->json('data.id'));

        $this->assertTrue($narration->matter->is($matter));
        $this->assertTrue($narration->author->is($user));
        $this->assertTrue($narration->activityType->is($type));
    }

    public function test_work_logged_without_a_time_is_dated_now(): void
    {
        $this->freezeSecond();
        $user = $this->signInAs(Role::CandidateAttorney);
        $matter = $this->carriedBy($user);

        $this->postJson("/api/v1/matters/{$matter->id}/narrations", [
            'activity_type_id' => ActivityType::factory()->create()->id,
            'body' => 'Read the file',
            'quantity' => 6,
        ])
            ->assertCreated()
            ->assertJsonPath('data.attributes.occurred_at', now()->toIso8601String());
    }

    public function test_only_those_carrying_an_open_matter_may_log_work_on_it(): void
    {
        $this->signInAs(Role::Secretary);
        $seen = Matter::factory()->create();
        $payload = ['activity_type_id' => ActivityType::factory()->create()->id, 'body' => 'Filed', 'quantity' => 6];

        $this->postJson("/api/v1/matters/{$seen->id}/narrations", $payload)->assertJsonApiError(403);

        $user = $this->signInAs(Role::Attorney);
        $closed = $this->carriedBy($user, Matter::factory()->closed()->create());

        $this->postJson("/api/v1/matters/{$closed->id}/narrations", $payload)->assertJsonApiError(403);
        $this->assertSame(0, Narration::query()->count());
    }

    public function test_logging_on_a_matter_the_user_may_not_see_is_not_found(): void
    {
        $this->signInAs(Role::CandidateAttorney);
        $matter = Matter::factory()->create();

        $this->postJson("/api/v1/matters/{$matter->id}/narrations", [
            'activity_type_id' => ActivityType::factory()->create()->id,
            'body' => 'Filed',
            'quantity' => 6,
        ])->assertJsonApiError(404);
    }

    public function test_the_work_logged_is_checked(): void
    {
        $user = $this->signInAs(Role::CandidateAttorney);
        $matter = $this->carriedBy($user);

        $this->postJson("/api/v1/matters/{$matter->id}/narrations", [])
            ->assertJsonApiError(422, '/activity_type_id')
            ->assertJsonApiError(422, '/body')
            ->assertJsonApiError(422, '/quantity');

        $this->postJson("/api/v1/matters/{$matter->id}/narrations", [
            'activity_type_id' => ActivityType::factory()->create()->id,
            'body' => 'Later',
            'quantity' => 0,
            'occurred_at' => now()->addDay()->toIso8601String(),
        ])
            ->assertJsonApiError(422, '/quantity')
            ->assertJsonApiError(422, '/occurred_at');
    }

    public function test_court_work_needs_the_matter_to_have_a_court(): void
    {
        $user = $this->signInAs(Role::CandidateAttorney);
        $matter = $this->carriedBy($user, Matter::factory()->create(['court_id' => null]));

        $this->postJson("/api/v1/matters/{$matter->id}/narrations", [
            'activity_type_id' => ActivityType::factory()->appearance()->create()->id,
            'body' => 'Appeared in court',
            'quantity' => 60,
        ])->assertJsonApiError(422, '/activity_type_id', 'This work is before a court and the matter has none yet. Set the court on the matter first.');
    }

    private function carriedBy(User $user, ?Matter $matter = null): Matter
    {
        $matter ??= Matter::factory()->create();
        MatterAssignment::factory()->for($matter)->for($user)->create();

        return $matter;
    }
}
