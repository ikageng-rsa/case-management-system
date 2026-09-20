<?php

namespace Tests\Unit\Models;

use App\Enums\Matter\Assignment;
use App\Models\Matter;
use App\Models\MatterAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatterAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_records_when_the_assignment_was_made(): void
    {
        $assignment = MatterAssignment::factory()->create(['assigned_at' => null]);

        $this->assertNotNull($assignment->assigned_at);
    }

    public function test_it_is_active_until_it_is_unassigned(): void
    {
        $assignment = MatterAssignment::factory()->create();

        $this->assertTrue($assignment->isActive());

        $assignment->unassign();

        $this->assertFalse($assignment->isActive());
        $this->assertNotNull($assignment->unassigned_at);
    }

    public function test_it_keeps_the_record_after_unassigning(): void
    {
        $assignment = MatterAssignment::factory()->create();

        $assignment->unassign();

        $this->assertDatabaseCount('matter_assignments', 1);
    }

    public function test_it_scopes_to_active_assignments_in_a_capacity(): void
    {
        $matter = Matter::factory()->create();

        MatterAssignment::factory()->for($matter)->capacity(Assignment::Responsible)->create();
        MatterAssignment::factory()->for($matter)->capacity(Assignment::Assisting)->create();
        MatterAssignment::factory()->for($matter)->capacity(Assignment::Responsible)->unassigned()->create();

        $found = MatterAssignment::active()->inCapacity(Assignment::Responsible)->get();

        $this->assertCount(1, $found);
    }

    public function test_it_deletes_assignments_with_the_matter(): void
    {
        $matter = Matter::factory()->create();
        MatterAssignment::factory()->for($matter)->create();

        $matter->forceDelete();

        $this->assertSame(0, MatterAssignment::query()->count());
    }

    public function test_a_user_reaches_their_matters_through_assignments(): void
    {
        $user = User::factory()->create();
        $matter = Matter::factory()->create();

        MatterAssignment::factory()->for($matter)->for($user)->create();

        $this->assertTrue($user->matters->contains($matter));
        $this->assertCount(1, $user->matterAssignments);
    }
}
