<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Matter;

use App\Enums\Auth\Role;
use App\Enums\Matter\Assignment;
use App\Models\Matter\Matter;
use App\Models\Matter\MatterAssignment;
use App\Models\User\User;
use App\Notifications\MatterAssigned;
use App\Notifications\MatterUnassigned;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Api\ApiTestCase;

class MatterAssignmentTest extends ApiTestCase
{
    public function test_the_team_needs_a_token(): void
    {
        $matter = Matter::factory()->create();

        $this->getJson("/api/v1/matters/{$matter->id}/assignments")->assertJsonApiError(401);
        $this->postJson("/api/v1/matters/{$matter->id}/assignments")->assertJsonApiError(401);
    }

    public function test_the_current_team_is_listed_with_each_persons_name(): void
    {
        $this->signInAs(Role::Secretary);
        $matter = Matter::factory()->create();
        $person = User::factory()->create(['name' => 'Naledi Khumalo', 'email' => 'naledi@example.com']);
        $current = MatterAssignment::factory()->for($matter)->for($person)->capacity(Assignment::Supervising)->create();
        MatterAssignment::factory()->for($matter)->unassigned()->create();

        $response = $this->getJson("/api/v1/matters/{$matter->id}/assignments")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'matter-assignments')
            ->assertJsonPath('data.0.id', (string) $current->id)
            ->assertJsonPath('data.0.attributes.capacity', 'supervising')
            ->assertJsonPath('data.0.relationships.user.data', ['id' => $person->id, 'type' => 'users'])
            ->assertJsonPath('included.0.attributes.name', 'Naledi Khumalo');

        $this->assertStringNotContainsString('naledi@example.com', $response->getContent());
    }

    public function test_the_team_of_a_matter_the_user_may_not_see_is_not_found(): void
    {
        $this->signInAs(Role::CandidateAttorney);
        $matter = Matter::factory()->create();

        $this->getJson("/api/v1/matters/{$matter->id}/assignments")->assertJsonApiError(404);
    }

    public function test_someone_is_assigned_and_told(): void
    {
        Notification::fake();
        $this->signInAs(Role::Director);
        $matter = Matter::factory()->create();
        $attorney = User::factory()->create();

        $this->postJson("/api/v1/matters/{$matter->id}/assignments", ['user_id' => $attorney->id, 'capacity' => 'responsible'])
            ->assertCreated()
            ->assertJsonPath('data.type', 'matter-assignments')
            ->assertJsonPath('data.attributes.capacity', 'responsible')
            ->assertJsonPath('data.relationships.user.data.id', $attorney->id);

        $this->assertTrue($matter->fresh()->isAssignedTo($attorney));
        Notification::assertSentTo($attorney, MatterAssigned::class);
    }

    public function test_taking_a_seat_held_by_one_person_retires_its_holder(): void
    {
        $this->signInAs(Role::Director);
        $matter = Matter::factory()->create();
        $previous = MatterAssignment::factory()->for($matter)->capacity(Assignment::Responsible)->create();
        $attorney = User::factory()->create();

        $this->postJson("/api/v1/matters/{$matter->id}/assignments", ['user_id' => $attorney->id, 'capacity' => 'responsible'])
            ->assertCreated();

        $this->assertFalse($previous->fresh()->isActive());
    }

    public function test_asking_again_for_a_seat_already_held_changes_nothing(): void
    {
        $this->signInAs(Role::Director);
        $matter = Matter::factory()->create();
        $existing = MatterAssignment::factory()->for($matter)->capacity(Assignment::Assisting)->create();

        $this->postJson("/api/v1/matters/{$matter->id}/assignments", ['user_id' => $existing->user_id, 'capacity' => 'assisting'])
            ->assertOk()
            ->assertJsonPath('data.id', (string) $existing->id);

        $this->assertSame(1, $matter->assignments()->count());
    }

    public function test_assigning_needs_the_assign_permission(): void
    {
        $user = $this->signInAs(Role::CandidateAttorney);
        $matter = Matter::factory()->create();
        MatterAssignment::factory()->for($matter)->for($user)->capacity(Assignment::Assisting)->create();

        $this->postJson("/api/v1/matters/{$matter->id}/assignments", ['user_id' => $user->id, 'capacity' => 'responsible'])
            ->assertJsonApiError(403);
    }

    public function test_assigning_on_a_matter_the_user_may_not_see_is_not_found(): void
    {
        $this->signInAs(Role::Attorney);
        $matter = Matter::factory()->create();

        $this->postJson("/api/v1/matters/{$matter->id}/assignments", ['user_id' => User::factory()->create()->id, 'capacity' => 'assisting'])
            ->assertJsonApiError(404);
    }

    public function test_a_closed_matter_takes_no_new_assignments(): void
    {
        $this->signInAs(Role::Director);
        $matter = Matter::factory()->closed()->create();

        $this->postJson("/api/v1/matters/{$matter->id}/assignments", ['user_id' => User::factory()->create()->id, 'capacity' => 'assisting'])
            ->assertJsonApiError(409, detail: "Matter {$matter->reference} is closed and cannot take new assignments.");
    }

    public function test_the_person_and_capacity_are_checked(): void
    {
        $this->signInAs(Role::Director);
        $matter = Matter::factory()->create();
        $leaver = User::factory()->create();
        $leaver->delete();

        $this->postJson("/api/v1/matters/{$matter->id}/assignments", [])
            ->assertJsonApiError(422, '/user_id')
            ->assertJsonApiError(422, '/capacity');

        $this->postJson("/api/v1/matters/{$matter->id}/assignments", ['user_id' => $leaver->id, 'capacity' => 'boss'])
            ->assertJsonApiError(422, '/user_id')
            ->assertJsonApiError(422, '/capacity');
    }

    public function test_someone_is_taken_off_the_matter_and_told(): void
    {
        Notification::fake();
        $this->signInAs(Role::Director);
        $matter = Matter::factory()->create();
        $assignment = MatterAssignment::factory()->for($matter)->create();

        $this->deleteJson("/api/v1/matters/{$matter->id}/assignments/{$assignment->id}")->assertNoContent();

        $this->assertFalse($assignment->fresh()->isActive());
        Notification::assertSentTo($assignment->user, MatterUnassigned::class);
    }

    public function test_an_assignment_already_ended_or_on_another_matter_is_not_found(): void
    {
        $this->signInAs(Role::Director);
        $matter = Matter::factory()->create();
        $ended = MatterAssignment::factory()->for($matter)->unassigned()->create();
        $elsewhere = MatterAssignment::factory()->create();

        $this->deleteJson("/api/v1/matters/{$matter->id}/assignments/{$ended->id}")->assertJsonApiError(404);
        $this->deleteJson("/api/v1/matters/{$matter->id}/assignments/{$elsewhere->id}")->assertJsonApiError(404);

        $this->assertTrue($elsewhere->fresh()->isActive());
    }

    public function test_unassigning_needs_the_assign_permission(): void
    {
        $user = $this->signInAs(Role::CandidateAttorney);
        $matter = Matter::factory()->create();
        $mine = MatterAssignment::factory()->for($matter)->for($user)->create();

        $this->deleteJson("/api/v1/matters/{$matter->id}/assignments/{$mine->id}")->assertJsonApiError(403);

        $this->assertTrue($mine->fresh()->isActive());
    }
}
