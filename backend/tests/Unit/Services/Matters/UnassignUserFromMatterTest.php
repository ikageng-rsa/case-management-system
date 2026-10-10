<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Matters;

use App\Enums\Matter\Assignment;
use App\Models\Matter;
use App\Models\User;
use App\Services\Matters\AssignUserToMatter;
use App\Services\Matters\UnassignUserFromMatter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnassignUserFromMatterTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_retires_an_active_assignment_but_keeps_the_history(): void
    {
        $matter = Matter::factory()->create();
        $user = User::factory()->create();
        $assignment = app(AssignUserToMatter::class)->assign($matter, $user, Assignment::Assisting);

        app(UnassignUserFromMatter::class)->unassign($matter, $user, Assignment::Assisting);

        $this->assertFalse($assignment->fresh()->isActive());
        $this->assertNotNull($assignment->fresh()->unassigned_at);
        $this->assertSame(1, $matter->assignments()->count());
        $this->assertFalse($matter->isAssignedTo($user));
    }

    public function test_it_is_a_no_op_when_the_user_is_not_assigned(): void
    {
        $matter = Matter::factory()->create();
        $user = User::factory()->create();

        app(UnassignUserFromMatter::class)->unassign($matter, $user, Assignment::Assisting);

        $this->assertSame(0, $matter->assignments()->count());
    }
}
