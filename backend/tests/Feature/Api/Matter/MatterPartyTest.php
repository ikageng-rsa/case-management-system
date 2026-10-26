<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Matter;

use App\Enums\Auth\Role;
use App\Enums\Matter\PartyRole;
use App\Models\Matter\Matter;
use App\Models\Matter\MatterAssignment;
use App\Models\Matter\MatterParty;
use Tests\Feature\Api\ApiTestCase;

class MatterPartyTest extends ApiTestCase
{
    public function test_parties_need_a_token(): void
    {
        $matter = Matter::factory()->create();

        $this->getJson("/api/v1/matters/{$matter->id}/parties")->assertJsonApiError(401);
    }

    public function test_a_matters_parties_are_listed_in_the_order_they_were_added(): void
    {
        $this->signInAs(Role::Secretary);
        $matter = Matter::factory()->create();
        $this->travelTo(now()->subDay());
        $first = MatterParty::factory()->for($matter)->role(PartyRole::ThirdParty)->create(['name' => 'Road Accident Fund', 'representative' => 'State Attorney']);
        $this->travelBack();
        $second = MatterParty::factory()->for($matter)->create();
        MatterParty::factory()->create();

        $this->getJson("/api/v1/matters/{$matter->id}/parties")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.type', 'matter-parties')
            ->assertJsonPath('data.0.id', $first->id)
            ->assertJsonPath('data.0.attributes', [
                'name' => 'Road Accident Fund',
                'role' => 'third_party',
                'role_label' => 'Third Party',
                'representative' => 'State Attorney',
            ])
            ->assertJsonPath('data.1.id', $second->id);
    }

    public function test_an_assigned_user_sees_the_parties_of_their_matter(): void
    {
        $user = $this->signInAs(Role::CandidateAttorney);
        $matter = Matter::factory()->create();
        MatterAssignment::factory()->for($matter)->for($user)->create();
        MatterParty::factory()->for($matter)->create();

        $this->getJson("/api/v1/matters/{$matter->id}/parties")->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_a_matter_with_no_parties_gives_an_empty_list(): void
    {
        $this->signInAs(Role::Secretary);
        $matter = Matter::factory()->create();

        $this->getJson("/api/v1/matters/{$matter->id}/parties")->assertOk()->assertExactJson(['data' => []]);
    }

    public function test_the_parties_of_a_matter_the_user_may_not_see_are_not_found(): void
    {
        $this->signInAs(Role::CandidateAttorney);
        $matter = Matter::factory()->create();
        MatterParty::factory()->for($matter)->create();

        $this->getJson("/api/v1/matters/{$matter->id}/parties")->assertJsonApiError(404);
    }
}
