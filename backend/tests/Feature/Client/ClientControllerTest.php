<?php

declare(strict_types=1);

namespace Tests\Feature\Client;

use App\Enums\Auth\Role as RoleEnum;
use App\Enums\Client\ContactKind;
use App\Enums\Matter\Assignment;
use App\Models\Client;
use App\Models\Matter;
use App\Models\MatterAssignment;
use App\Models\User;
use App\Services\Clients\AddClientContact;
use App\Services\Clients\MaskIdentifier;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientControllerTest extends TestCase
{
    use RefreshDatabase;

    private const ID_NUMBER = '9001015800088';

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

    public function test_the_page_is_gated_behind_view_clients(): void
    {
        $client = Client::factory()->create();

        $messenger = $this->userWithRole(RoleEnum::Messenger);

        $this->actingAs($messenger)->get(route('clients'))->assertForbidden();
        $this->actingAs($messenger)->get(route('client.show', $client))->assertForbidden();
    }

    public function test_the_index_lands_on_the_first_client(): void
    {
        Client::factory()->create(['first_name' => 'Zola', 'last_name' => 'Zulu']);
        $first = Client::factory()->create(['first_name' => 'Abel', 'last_name' => 'Adams']);

        $this->actingAs($this->userWithRole(RoleEnum::Secretary))
            ->get(route('clients'))
            ->assertRedirect(route('client.show', $first));
    }

    public function test_the_index_renders_empty_when_there_are_no_clients(): void
    {
        $this->actingAs($this->userWithRole(RoleEnum::Secretary))
            ->get(route('clients'))
            ->assertOk()
            ->assertSee('No clients to show.');
    }

    public function test_the_list_can_be_searched_by_name_and_by_whole_identifier(): void
    {
        $abel = Client::factory()->create(['first_name' => 'Abel', 'last_name' => 'Adams', 'id_number' => self::ID_NUMBER]);
        $zola = Client::factory()->create(['first_name' => 'Zola', 'last_name' => 'Zulu']);
        $entity = Client::factory()->entity()->create(['entity_name' => 'Rustenburg Plat', 'registration_number' => '2011/123456/07']);

        $secretary = $this->userWithRole(RoleEnum::Secretary);

        $this->actingAs($secretary)->get(route('client.show', [$zola, 'q' => 'zul']))
            ->assertViewHas('clients', fn ($clients) => $clients->pluck('id')->all() === [$zola->id]);

        $this->actingAs($secretary)->get(route('client.show', [$zola, 'q' => '9001 015800 088']))
            ->assertViewHas('clients', fn ($clients) => $clients->pluck('id')->all() === [$abel->id]);

        $this->actingAs($secretary)->get(route('client.show', [$zola, 'q' => '2011/123456/07']))
            ->assertViewHas('clients', fn ($clients) => $clients->pluck('id')->all() === [$entity->id]);

        // Text that cannot be an ID number must not match every entity through a NULL hash.
        $this->actingAs($secretary)->get(route('client.show', [$zola, 'q' => 'nobody']))
            ->assertViewHas('clients', fn ($clients) => $clients->isEmpty());
    }

    public function test_the_list_can_be_searched_by_phone_number(): void
    {
        $client = Client::factory()->create();
        Client::factory()->create();
        app(AddClientContact::class)->add($client, ContactKind::Mobile, '072 123 4418');

        $this->actingAs($this->userWithRole(RoleEnum::Secretary))
            ->get(route('client.show', [$client, 'q' => '0721234418']))
            ->assertViewHas('clients', fn ($clients) => $clients->pluck('id')->all() === [$client->id]);
    }

    public function test_the_detail_shows_the_clients_matters_and_open_count(): void
    {
        $client = Client::factory()->create();
        Matter::factory()->for($client)->create(['reference' => 'IOT/CIV/1/2026']);
        Matter::factory()->for($client)->closed()->create(['reference' => 'IOT/CIV/2/2026']);
        Matter::factory()->create(['reference' => 'ELSE/CIV/3/2026']);

        $this->actingAs($this->userWithRole(RoleEnum::Director))
            ->get(route('client.show', $client))
            ->assertOk()
            ->assertSee('IOT/CIV/1/2026')
            ->assertSee('IOT/CIV/2/2026')
            ->assertDontSee('ELSE/CIV/3/2026')
            ->assertViewHas('openMattersCount', 1);
    }

    public function test_a_user_only_sees_the_clients_matters_they_carry_unless_they_view_all(): void
    {
        $client = Client::factory()->create();
        $mine = Matter::factory()->for($client)->create(['reference' => 'IOT/CIV/1/2026']);
        Matter::factory()->for($client)->create(['reference' => 'IOT/CIV/2/2026']);

        $attorney = $this->userWithRole(RoleEnum::Attorney);
        MatterAssignment::factory()->for($mine)->for($attorney)->capacity(Assignment::Responsible)->create();

        $this->actingAs($attorney)
            ->get(route('client.show', $client))
            ->assertSee('IOT/CIV/1/2026')
            ->assertDontSee('IOT/CIV/2/2026');
    }

    public function test_identifiers_and_contact_details_are_always_shown_masked(): void
    {
        $client = Client::factory()->create(['id_number' => self::ID_NUMBER]);
        app(AddClientContact::class)->add($client, ContactKind::Mobile, '0721234418');
        app(AddClientContact::class)->add($client, ContactKind::Email, 'thabo@example.com');

        foreach ([RoleEnum::Director, RoleEnum::Secretary] as $role) {
            $this->actingAs($this->userWithRole($role))
                ->get(route('client.show', $client))
                ->assertSee('072 ··· 4418')
                ->assertSee('thabo@·······.com')
                ->assertSee('9001015······')
                ->assertDontSee('0721234418')
                ->assertDontSee('+27721234418')
                ->assertDontSee('thabo@example.com')
                ->assertDontSee(self::ID_NUMBER);
        }
    }

    public function test_contact_details_are_withheld_without_the_contacts_permission(): void
    {
        $client = Client::factory()->create();
        app(AddClientContact::class)->add($client, ContactKind::Email, 'thabo@example.com');

        $this->actingAs($this->userWithRole(RoleEnum::Manager))
            ->get(route('client.show', $client))
            ->assertOk()
            ->assertDontSee('thabo@')
            ->assertDontSee('····.com');
    }

    public function test_opening_a_client_is_recorded_in_the_audit_log(): void
    {
        $client = Client::factory()->create();

        $this->actingAs($this->userWithRole(RoleEnum::Secretary))->get(route('client.show', $client));

        $this->assertDatabaseHas('activity_log', ['event' => 'read', 'subject_id' => $client->id]);
    }

    public function test_an_individual_can_be_registered_with_contacts(): void
    {
        $this->actingAs($this->userWithRole(RoleEnum::Secretary))
            ->post(route('client.store'), [
                'type' => 'individual',
                'first_name' => 'Thabo',
                'last_name' => 'Mokoena',
                'id_number' => self::ID_NUMBER,
                'mobile' => '072 123 4418',
                'email' => 'thabo@example.com',
            ])
            ->assertSessionHasNoErrors();

        $client = Client::firstOrFail();

        $this->assertSame('Thabo Mokoena', $client->full_name);
        $this->assertCount(2, $client->contacts);
    }

    public function test_an_entity_can_be_registered_without_contacts(): void
    {
        $this->actingAs($this->userWithRole(RoleEnum::Secretary))
            ->post(route('client.store'), [
                'type' => 'entity',
                'entity_name' => 'Rustenburg Plat (Pty) Ltd',
                'registration_number' => '2011/123456/07',
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Client::firstOrFail()->isEntity());
    }

    public function test_a_duplicate_client_is_refused_and_nothing_is_half_saved(): void
    {
        Client::factory()->create(['id_number' => self::ID_NUMBER]);

        $this->actingAs($this->userWithRole(RoleEnum::Secretary))
            ->post(route('client.store'), [
                'type' => 'individual',
                'first_name' => 'Thabo',
                'last_name' => 'Mokoena',
                'id_number' => self::ID_NUMBER,
            ])
            ->assertSessionHasErrorsIn('client');

        $this->assertDatabaseCount('clients', 1);
    }

    public function test_registering_needs_the_create_permission(): void
    {
        $this->actingAs($this->userWithRole(RoleEnum::Manager))
            ->post(route('client.store'), ['type' => 'entity', 'entity_name' => 'X', 'registration_number' => '2011/123456/07'])
            ->assertForbidden();

        $this->assertDatabaseCount('clients', 0);
    }

    public function test_identifiers_are_masked_enough_to_recognise_but_not_reuse(): void
    {
        $this->assertSame('072 ··· 4418', MaskIdentifier::mobile('+27721234418'));
        $this->assertSame('t.mokoena@·······.com', MaskIdentifier::email('t.mokoena@example.com'));
        $this->assertSame('9001015······', MaskIdentifier::idNumber(self::ID_NUMBER));
        $this->assertSame('2011/······/07', MaskIdentifier::registrationNumber('2011/123456/07'));
    }
}
