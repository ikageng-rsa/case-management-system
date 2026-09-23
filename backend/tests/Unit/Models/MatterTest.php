<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\Matter\Assignment;
use App\Models\Client;
use App\Models\Matter;
use App\Models\MatterAssignment;
use App\Models\MatterType;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MatterTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_gets_a_uuid_primary_key(): void
    {
        $matter = Matter::factory()->create();

        $this->assertTrue(Str::isUuid($matter->id));
    }

    public function test_it_belongs_to_a_client_a_type_and_a_court(): void
    {
        $matter = Matter::factory()->create();

        $this->assertInstanceOf(Client::class, $matter->client);
        $this->assertInstanceOf(MatterType::class, $matter->matterType);
        $this->assertNotNull($matter->court);
    }

    public function test_a_non_litigious_matter_has_no_court(): void
    {
        $matter = Matter::factory()->nonLitigation()->create();

        $this->assertNull($matter->court);
        $this->assertFalse($matter->matterType->is_litigation);
    }

    public function test_it_is_open_until_it_is_closed(): void
    {
        $open = Matter::factory()->create();
        $closed = Matter::factory()->closed()->create();

        $this->assertTrue($open->isOpen());
        $this->assertTrue($closed->isClosed());
        $this->assertCount(1, Matter::open()->get());
        $this->assertCount(1, Matter::closed()->get());
    }

    public function test_it_finds_matters_prescribing_within_a_window(): void
    {
        $soon = Matter::factory()->prescribesIn(20)->create();
        Matter::factory()->prescribesIn(90)->create();
        Matter::factory()->nonLitigation()->create();

        $found = Matter::prescribingWithin(30)->get();

        $this->assertCount(1, $found);
        $this->assertTrue($found->first()->is($soon));
    }

    public function test_it_excludes_closed_matters_from_the_prescription_warning(): void
    {
        Matter::factory()->prescribesIn(20)->closed()->create();

        $this->assertCount(0, Matter::prescribingWithin(30)->get());
    }

    public function test_it_reports_a_matter_that_has_already_prescribed(): void
    {
        $prescribed = Matter::factory()->prescribed()->create();

        $this->assertTrue($prescribed->hasPrescribed());
        $this->assertFalse(Matter::factory()->create()->hasPrescribed());
        $this->assertCount(1, Matter::prescribed()->get());
    }

    public function test_a_matter_without_a_prescription_date_never_prescribes(): void
    {
        $matter = Matter::factory()->nonLitigation()->create();

        $this->assertFalse($matter->hasPrescribed());
    }

    public function test_it_resolves_the_responsible_and_supervising_attorneys(): void
    {
        $matter = Matter::factory()->create();
        $responsible = User::factory()->create();
        $supervisor = User::factory()->create();

        MatterAssignment::factory()->for($matter)->for($responsible)
            ->capacity(Assignment::Responsible)->create();
        MatterAssignment::factory()->for($matter)->for($supervisor)
            ->capacity(Assignment::Supervising)->create();

        $matter->load('assignments');

        $this->assertTrue($matter->responsibleAttorney()->is($responsible));
        $this->assertTrue($matter->supervisingAttorney()->is($supervisor));
    }

    public function test_it_ignores_attorneys_who_have_been_unassigned(): void
    {
        $matter = Matter::factory()->create();
        $former = User::factory()->create();

        MatterAssignment::factory()->for($matter)->for($former)
            ->capacity(Assignment::Responsible)->unassigned()->create();

        $matter->load('assignments');

        $this->assertNull($matter->responsibleAttorney());
        $this->assertFalse($matter->isAssignedTo($former));
    }

    public function test_it_reports_who_a_matter_is_assigned_to(): void
    {
        $matter = Matter::factory()->create();
        $user = User::factory()->create();

        MatterAssignment::factory()->for($matter)->for($user)->create();

        $this->assertTrue($matter->load('assignments')->isAssignedTo($user));
        $this->assertTrue($matter->assignedUsers->contains($user));
    }

    public function test_it_enforces_one_sequence_number_per_type_and_year(): void
    {
        $type = MatterType::factory()->create();
        Matter::factory()->for($type, 'matterType')->create([
            'sequence_number' => 1,
            'opened_year' => 2026,
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        Matter::factory()->for($type, 'matterType')->create([
            'sequence_number' => 1,
            'opened_year' => 2026,
        ]);
    }
}
