<?php

declare(strict_types=1);

namespace Tests\Feature\Matter;

use App\Enums\Auth\Role as RoleEnum;
use App\Enums\Matter\Assignment;
use App\Models\ActivityType;
use App\Models\Matter;
use App\Models\MatterAssignment;
use App\Models\Narration;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatterShowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RoleSeeder::class);
    }

    protected function userWithRole(RoleEnum $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);

        return $user;
    }

    public function test_the_url_uses_hyphens_in_place_of_the_slashes_in_a_reference(): void
    {
        $matter = Matter::factory()->create(['reference' => 'IOT/CIV/105/2026']);

        $this->assertSame('IOT-CIV-105-2026', $matter->getRouteKey());

        $this->actingAs($this->userWithRole(RoleEnum::Director))
            ->get(route('matter.show', $matter))
            ->assertOk()
            ->assertSee('IOT/CIV/105/2026');
    }

    public function test_a_user_cannot_open_a_matter_they_are_not_assigned_to(): void
    {
        $matter = Matter::factory()->create();

        $this->actingAs($this->userWithRole(RoleEnum::Attorney))
            ->get(route('matter.show', $matter))
            ->assertForbidden();
    }

    public function test_every_tab_renders(): void
    {
        $matter = Matter::factory()->create();

        $director = $this->userWithRole(RoleEnum::Director);

        foreach (['ledger', 'documents', 'parties', 'diary', 'audit'] as $tab) {
            $this->actingAs($director)
                ->get(route('matter.show', [$matter, 'tab' => $tab]))
                ->assertOk()
                ->assertViewHas('tab', $tab);
        }
    }

    public function test_the_audit_tab_is_hidden_from_users_without_the_permission(): void
    {
        $matter = Matter::factory()->create();
        $user = $this->userWithRole(RoleEnum::Secretary);

        $this->actingAs($user)
            ->get(route('matter.show', [$matter, 'tab' => 'audit']))
            ->assertOk()
            ->assertViewHas('tab', 'ledger')
            ->assertDontSee('Audit trail');
    }

    public function test_the_audit_tab_lists_what_happened_on_the_matter(): void
    {
        $matter = Matter::factory()->create();
        $director = $this->userWithRole(RoleEnum::Director);

        $this->actingAs($director)->get(route('matter.show', $matter));

        $this->actingAs($director)
            ->get(route('matter.show', [$matter, 'tab' => 'audit']))
            ->assertOk()
            ->assertSee('read matter '.$matter->reference);
    }

    public function test_the_ledger_can_be_filtered_by_activity_type_and_author(): void
    {
        $matter = Matter::factory()->create();
        $director = $this->userWithRole(RoleEnum::Director);

        $call = ActivityType::factory()->create(['code' => 'CALL']);
        $filing = ActivityType::factory()->create(['code' => 'FILE']);

        Narration::factory()->for($matter)->create(['activity_type_id' => $call->getKey(), 'author_id' => $director->getKey()]);
        Narration::factory()->for($matter)->count(2)->create(['activity_type_id' => $filing->getKey()]);

        $this->actingAs($director)
            ->get(route('matter.show', [$matter, 'filter' => 'FILE']))
            ->assertViewHas('narrations', fn ($narrations) => $narrations->count() === 2)
            ->assertViewHas('allCount', 3);

        $this->actingAs($director)
            ->get(route('matter.show', [$matter, 'filter' => 'mine']))
            ->assertViewHas('narrations', fn ($narrations) => $narrations->count() === 1);
    }

    public function test_the_ledger_exports_as_csv(): void
    {
        $matter = Matter::factory()->create();
        Narration::factory()->for($matter)->create(['body' => 'Called the client']);

        $this->actingAs($this->userWithRole(RoleEnum::Director))
            ->get(route('matter.narrations.export', $matter))
            ->assertOk()
            ->assertDownload()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_parties_shows_the_team_and_lets_an_authorised_user_assign_and_unassign(): void
    {
        $matter = Matter::factory()->create();
        $attorney = $this->userWithRole(RoleEnum::Attorney);
        $director = $this->userWithRole(RoleEnum::Director);

        $this->actingAs($director)
            ->post(route('matter.assign', $matter), ['user_id' => $attorney->getKey(), 'capacity' => 'responsible'])
            ->assertRedirect(route('matter.show', [$matter, 'tab' => 'parties']));

        $this->assertTrue($matter->fresh()->load('assignments')->isAssignedTo($attorney));

        $this->actingAs($director)
            ->delete(route('matter.unassign', $matter), ['user_id' => $attorney->getKey(), 'capacity' => 'responsible'])
            ->assertRedirect();

        $this->assertFalse($matter->fresh()->load('assignments')->isAssignedTo($attorney));
    }

    public function test_assigning_requires_the_permission(): void
    {
        $matter = Matter::factory()->create();
        $candidate = $this->userWithRole(RoleEnum::CandidateAttorney);
        MatterAssignment::factory()->for($matter)->for($candidate)->capacity(Assignment::Assisting)->create();

        $this->actingAs($candidate)
            ->post(route('matter.assign', $matter), ['user_id' => $candidate->getKey(), 'capacity' => 'responsible'])
            ->assertForbidden();
    }

    public function test_assigning_to_a_closed_matter_reports_the_reason(): void
    {
        $matter = Matter::factory()->closed()->create();
        $attorney = $this->userWithRole(RoleEnum::Attorney);

        $this->actingAs($this->userWithRole(RoleEnum::Director))
            ->post(route('matter.assign', $matter), ['user_id' => $attorney->getKey(), 'capacity' => 'assisting'])
            ->assertSessionHasErrorsIn('assignment', 'matter');
    }

    public function test_the_assign_sheet_lists_people_only_for_users_who_may_assign(): void
    {
        $matter = Matter::factory()->create();
        $attorney = $this->userWithRole(RoleEnum::Attorney);
        MatterAssignment::factory()->for($matter)->for($attorney)->capacity(Assignment::Assisting)->create();

        $this->actingAs($this->userWithRole(RoleEnum::Director))
            ->get(route('matter.show', $matter))
            ->assertViewHas('assignable', fn ($users) => $users->isNotEmpty());

        $this->actingAs($this->userWithRole(RoleEnum::CandidateAttorney)->fresh())
            ->get(route('matter.show', $matter))
            ->assertForbidden();
    }
}
