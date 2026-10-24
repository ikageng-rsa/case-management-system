<?php

declare(strict_types=1);

namespace Tests\Unit\Queries\Matters;

use App\Models\ClientContact;
use App\Models\Matter;
use App\Models\MatterParty;
use App\Models\User;
use App\Queries\Matters\MatterParties;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatterPartiesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_it_lists_the_matters_parties_in_the_order_they_were_added(): void
    {
        $matter = Matter::factory()->create();
        $this->travelTo(now()->subDay());
        $first = MatterParty::factory()->for($matter)->create();
        $this->travelBack();
        $second = MatterParty::factory()->for($matter)->create();
        MatterParty::factory()->create();

        $parties = (new MatterParties)->for($matter, User::factory()->create());

        $this->assertSame([$first->id, $second->id], $parties['matterParties']->pluck('id')->all());
    }

    public function test_it_shows_the_clients_contacts_only_to_those_allowed_to_see_them(): void
    {
        $matter = Matter::factory()->create();
        $contact = ClientContact::factory()->for($matter->client)->create();
        $reader = User::factory()->create();
        $reader->givePermissionTo('view_client_contacts');

        $this->assertSame([$contact->id], (new MatterParties)->for($matter, $reader)['contacts']->pluck('id')->all());
        $this->assertTrue((new MatterParties)->for($matter, User::factory()->create())['contacts']->isEmpty());
    }

    public function test_it_says_whether_the_user_may_reveal_masked_details(): void
    {
        $matter = Matter::factory()->create();
        $revealer = User::factory()->create();
        $revealer->givePermissionTo('view_unmasked_client_data');

        $this->assertTrue((new MatterParties)->for($matter, $revealer)['canReveal']);
        $this->assertFalse((new MatterParties)->for($matter, User::factory()->create())['canReveal']);
    }
}
