<?php

declare(strict_types=1);

namespace Tests\Feature\Matter;

use App\Enums\Auth\Role as RoleEnum;
use App\Enums\Matter\PartyRole;
use App\Models\Client\Client;
use App\Models\Matter\Matter;
use App\Models\Matter\MatterParty;
use App\Models\Reference\MatterType;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class MatterPartiesTest extends TestCase
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

    public function test_the_parties_tab_lists_the_other_parties_with_their_role_and_representative(): void
    {
        $matter = Matter::factory()->create();
        MatterParty::factory()->for($matter)->create(['name' => 'Rustenburg Platinum Mines', 'role' => PartyRole::Respondent, 'representative' => 'Bowman Gilfillan']);
        MatterParty::factory()->for($matter)->create(['name' => 'Adv. S. Mahlangu', 'role' => PartyRole::Counsel]);

        $this->actingAs($this->userWithRole(RoleEnum::Director))
            ->get(route('matter.show', [$matter, 'tab' => 'parties']))
            ->assertOk()
            ->assertSee('Rustenburg Platinum Mines')
            ->assertSee('Respondent · represented by Bowman Gilfillan')
            ->assertSee('Adv. S. Mahlangu')
            ->assertSee('Counsel');
    }

    public function test_the_tab_says_so_when_there_are_none(): void
    {
        $this->actingAs($this->userWithRole(RoleEnum::Director))
            ->get(route('matter.show', [Matter::factory()->create(), 'tab' => 'parties']))
            ->assertSee('No other parties recorded.');
    }

    public function test_a_party_is_added_changed_and_removed(): void
    {
        $matter = Matter::factory()->create();
        $secretary = $this->userWithRole(RoleEnum::Secretary);

        $this->actingAs($secretary)
            ->post(route('matter.parties.store', $matter), ['name' => 'Rustenburg Platinum', 'role' => 'respondent', 'representative' => 'Cliffe Dekker'])
            ->assertRedirect(route('matter.show', [$matter, 'tab' => 'parties']))
            ->assertSessionHasNoErrors();

        $party = $matter->parties()->firstOrFail();

        $this->assertSame(PartyRole::Respondent, $party->role);
        $this->assertSame('Cliffe Dekker', $party->representative);

        $this->actingAs($secretary)
            ->patch(route('matter.parties.update', [$matter, $party]), ['name' => 'Rustenburg Platinum Mines', 'role' => 'defendant', 'representative' => ''])
            ->assertSessionHasNoErrors();

        $party->refresh();

        $this->assertSame('Rustenburg Platinum Mines', $party->name);
        $this->assertSame(PartyRole::Defendant, $party->role);
        $this->assertNull($party->representative);

        $this->actingAs($secretary)
            ->delete(route('matter.parties.destroy', [$matter, $party]))
            ->assertRedirect(route('matter.show', [$matter, 'tab' => 'parties']));

        $this->assertDatabaseMissing('matter_parties', ['id' => $party->getKey()]);
    }

    public function test_a_party_needs_a_name_and_a_known_role(): void
    {
        $matter = Matter::factory()->create();

        $this->actingAs($this->userWithRole(RoleEnum::Secretary))
            ->post(route('matter.parties.store', $matter), ['role' => 'overlord'])
            ->assertSessionHasErrorsIn('party_new', ['name', 'role']);

        $this->assertCount(0, $matter->parties);
    }

    public function test_a_closed_matter_keeps_its_parties_as_they_were(): void
    {
        $matter = Matter::factory()->closed()->create();
        $party = MatterParty::factory()->for($matter)->create(['name' => 'Original']);
        $secretary = $this->userWithRole(RoleEnum::Secretary);

        $this->actingAs($secretary)
            ->post(route('matter.parties.store', $matter), ['name' => 'New', 'role' => 'witness'])
            ->assertSessionHasErrorsIn('parties', 'party');

        $this->actingAs($secretary)
            ->patch(route('matter.parties.update', [$matter, $party]), ['name' => 'Changed', 'role' => 'witness'])
            ->assertSessionHasErrorsIn('parties', 'party');

        $this->actingAs($secretary)
            ->delete(route('matter.parties.destroy', [$matter, $party]))
            ->assertSessionHasErrorsIn('parties', 'party');

        $this->assertSame('Original', $party->fresh()->name);
        $this->assertCount(1, $matter->fresh()->parties);
    }

    public function test_changing_parties_needs_the_edit_matters_permission_and_the_right_matter(): void
    {
        $matter = Matter::factory()->create();
        $party = MatterParty::factory()->for($matter)->create();

        $this->actingAs($this->userWithRole(RoleEnum::CandidateAttorney))
            ->post(route('matter.parties.store', $matter), ['name' => 'X', 'role' => 'witness'])
            ->assertForbidden();

        $this->actingAs($this->userWithRole(RoleEnum::Secretary))
            ->delete(route('matter.parties.destroy', [Matter::factory()->create(), $party]))
            ->assertNotFound();

        $this->assertDatabaseHas('matter_parties', ['id' => $party->getKey()]);
    }

    public function test_changes_to_a_party_are_audited_once_and_shown_on_the_matters_audit_tab(): void
    {
        $matter = Matter::factory()->create();
        $party = MatterParty::factory()->for($matter)->create(['name' => 'Original']);
        Activity::query()->delete();

        $director = $this->userWithRole(RoleEnum::Director);

        $this->actingAs($director)
            ->patch(route('matter.parties.update', [$matter, $party]), ['name' => 'Renamed', 'role' => 'respondent']);

        $this->assertSame(1, Activity::forSubject($party)->where('event', 'updated')->count());

        $this->actingAs($director)
            ->get(route('matter.show', [$matter, 'tab' => 'audit']))
            ->assertSee('Renamed');
    }

    public function test_parties_can_be_given_when_a_matter_is_opened(): void
    {
        $client = Client::factory()->create();
        $type = MatterType::factory()->nonLitigation()->create();

        $this->actingAs($this->userWithRole(RoleEnum::Secretary))
            ->post(route('matter.store'), [
                'client_id' => $client->getKey(),
                'matter_type_id' => $type->getKey(),
                'parties' => [
                    ['name' => 'Rustenburg Platinum Mines', 'role' => 'respondent', 'representative' => 'Cliffe Dekker'],
                    ['name' => 'Adv. S. Mahlangu', 'role' => 'counsel', 'representative' => ''],
                ],
            ])
            ->assertSessionHasNoErrors();

        $matter = Matter::firstOrFail();

        $this->assertCount(2, $matter->parties);
        $this->assertSame('Cliffe Dekker', $matter->parties->firstWhere('name', 'Rustenburg Platinum Mines')->representative);
        $this->assertSame(PartyRole::Counsel, $matter->parties->firstWhere('name', 'Adv. S. Mahlangu')->role);
    }

    public function test_a_bad_party_stops_the_matter_being_opened(): void
    {
        $client = Client::factory()->create();
        $type = MatterType::factory()->nonLitigation()->create();

        $this->actingAs($this->userWithRole(RoleEnum::Secretary))
            ->post(route('matter.store'), [
                'client_id' => $client->getKey(),
                'matter_type_id' => $type->getKey(),
                'parties' => [['name' => '', 'role' => 'nonsense']],
            ])
            ->assertSessionHasErrorsIn('matter', ['parties.0.name', 'parties.0.role']);

        $this->assertDatabaseCount('matters', 0);
        $this->assertDatabaseCount('matter_parties', 0);
    }

    public function test_the_matter_search_finds_a_matter_by_a_party(): void
    {
        $matter = Matter::factory()->create(['reference' => 'AG/CIV/1/2026', 'title' => 'Plain']);
        Matter::factory()->create(['reference' => 'AG/CIV/2/2026', 'title' => 'Plain']);
        MatterParty::factory()->for($matter)->create(['name' => 'Rustenburg Platinum Mines', 'representative' => 'Cliffe Dekker']);

        $director = $this->userWithRole(RoleEnum::Director);

        $found = fn (string $term) => collect($this->actingAs($director)->getJson(route('matter.search', ['q' => $term]))->json())->pluck('reference')->all();

        $this->assertSame(['AG/CIV/1/2026'], $found('platinum'));
        $this->assertSame(['AG/CIV/1/2026'], $found('cliffe'));
    }

    public function test_the_intake_form_has_a_parties_step(): void
    {
        $this->actingAs($this->userWithRole(RoleEnum::Secretary))
            ->get(route('matters'))
            ->assertOk()
            ->assertSee("current === 'Parties'", false)
            ->assertSee('Add a party');
    }
}
