<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Matters;

use App\Enums\Matter\Assignment;
use App\Models\Matter;
use App\Models\MatterAssignment;
use App\Models\User;
use App\Services\Matters\AssignUserToMatter;
use App\Services\Matters\CannotAssignToClosedMatter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignUserToMatterTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_assigns_a_user_in_a_capacity(): void
    {
        $matter = Matter::factory()->create();
        $user = User::factory()->create();

        $assignment = $this->assign($matter, $user, Assignment::Assisting);

        $this->assertTrue($assignment->isActive());
        $this->assertSame(Assignment::Assisting, $assignment->capacity);
        $this->assertTrue($assignment->user->is($user));
        $this->assertNotNull($assignment->assigned_at);
    }

    public function test_it_is_idempotent_for_the_same_user_and_capacity(): void
    {
        $matter = Matter::factory()->create();
        $user = User::factory()->create();

        $first = $this->assign($matter, $user, Assignment::Assisting);
        $second = $this->assign($matter, $user, Assignment::Assisting);

        $this->assertTrue($first->is($second));
        $this->assertSame(1, $matter->assignments()->count());
    }

    public function test_an_exclusive_capacity_retires_the_incumbent(): void
    {
        $matter = Matter::factory()->create();
        $incumbent = User::factory()->create();
        $successor = User::factory()->create();

        $original = $this->assign($matter, $incumbent, Assignment::Responsible);
        $replacement = $this->assign($matter, $successor, Assignment::Responsible);

        $this->assertFalse($original->fresh()->isActive());
        $this->assertTrue($replacement->isActive());
        $this->assertTrue($matter->responsibleAttorney()->is($successor));
        $this->assertSame(2, $matter->assignments()->count());
    }

    public function test_a_shared_capacity_allows_several_active_holders(): void
    {
        $matter = Matter::factory()->create();

        $this->assign($matter, User::factory()->create(), Assignment::Assisting);
        $this->assign($matter, User::factory()->create(), Assignment::Assisting);

        $active = $matter->assignments()->active()->inCapacity(Assignment::Assisting)->count();

        $this->assertSame(2, $active);
    }

    public function test_it_rejects_assignment_to_a_closed_matter(): void
    {
        $matter = Matter::factory()->create(['closed_at' => now()]);
        $user = User::factory()->create();

        $this->expectException(CannotAssignToClosedMatter::class);

        $this->assign($matter, $user, Assignment::Assisting);
    }

    private function assign(Matter $matter, User $user, Assignment $capacity): MatterAssignment
    {
        return app(AssignUserToMatter::class)->assign($matter, $user, $capacity);
    }
}
