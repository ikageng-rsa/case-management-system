<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Client;

use App\Enums\Auth\Role;
use App\Enums\Client\IdentityType;
use App\Models\Client\Client;
use App\Models\Client\ClientContact;
use App\Models\Client\ClientContactPerson;
use App\Models\Matter\Matter;
use Spatie\Activitylog\Models\Activity;
use Tests\Feature\Api\ApiTestCase;

class ClientTest extends ApiTestCase
{
    public function test_clients_need_a_token(): void
    {
        $this->getJson('/api/v1/clients')->assertJsonApiError(401);
    }

    public function test_listing_clients_needs_view_clients(): void
    {
        $this->signInAs(Role::Messenger);

        $this->getJson('/api/v1/clients')->assertJsonApiError(403);
    }

    public function test_clients_are_listed_with_identifiers_masked(): void
    {
        $this->signInAs(Role::CandidateAttorney);
        $client = Client::factory()->create(['first_name' => 'Thandi', 'last_name' => 'Zulu', 'id_number' => '9001015800088']);
        Matter::factory()->for($client)->count(2)->create();

        $response = $this->getJson('/api/v1/clients')
            ->assertOk()
            ->assertJsonPath('data.0.type', 'clients')
            ->assertJsonPath('data.0.id', $client->id)
            ->assertJsonPath('data.0.attributes.name', 'Thandi Zulu')
            ->assertJsonPath('data.0.attributes.client_type', 'individual')
            ->assertJsonPath('data.0.attributes.identity_type', IdentityType::SouthAfricanId->value)
            ->assertJsonPath('data.0.attributes.identifier_masked', '·········0088')
            ->assertJsonPath('data.0.attributes.matters_count', 2)
            ->assertJsonPath('meta.total', 1);

        $this->assertStringNotContainsString('9001015800088', $response->getContent());
    }

    public function test_the_list_can_be_searched(): void
    {
        $this->signInAs(Role::CandidateAttorney);
        Client::factory()->create(['first_name' => 'Thandi', 'last_name' => 'Zulu']);
        Client::factory()->create(['first_name' => 'Sipho', 'last_name' => 'Mokoena']);

        $this->getJson('/api/v1/clients?q=zulu')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.name', 'Thandi Zulu');
    }

    public function test_the_list_does_not_carry_contacts(): void
    {
        $this->signInAs(Role::CandidateAttorney);
        ClientContact::factory()->for(Client::factory())->create();

        $this->getJson('/api/v1/clients?include=contacts')
            ->assertOk()
            ->assertJsonMissingPath('included');
    }

    public function test_a_client_is_shown_with_masked_contacts_to_those_who_may_see_contacts(): void
    {
        $this->signInAs(Role::CandidateAttorney);
        $client = Client::factory()->create();
        $mobile = ClientContact::factory()->mobile()->primary()->for($client)->create(['value' => '0821234567']);

        $response = $this->getJson("/api/v1/clients/{$client->id}")
            ->assertOk()
            ->assertJsonPath('data.relationships.contacts.data.0', ['id' => (string) $mobile->id, 'type' => 'client-contacts'])
            ->assertJsonPath('included.0.type', 'client-contacts')
            ->assertJsonPath('included.0.attributes.kind', 'mobile')
            ->assertJsonPath('included.0.attributes.value_masked', '082 ··· 4567')
            ->assertJsonPath('included.0.attributes.is_primary', true)
            ->assertJsonPath('data.meta.can', ['view_contacts' => true, 'reveal' => true]);

        $this->assertStringNotContainsString('0821234567', $response->getContent());
        $this->assertStringNotContainsString('1234567', $response->getContent());
    }

    public function test_an_entitys_contact_people_are_named_but_their_numbers_masked(): void
    {
        $this->signInAs(Role::CandidateAttorney);
        $client = Client::factory()->entity()->create(['registration_number' => '2011/123456/07']);
        ClientContactPerson::factory()->for($client)->create(['name' => 'Lerato Dube', 'mobile' => '0831112222', 'email' => 'lerato@example.com']);

        $this->getJson("/api/v1/clients/{$client->id}")
            ->assertOk()
            ->assertJsonPath('data.attributes.client_type', 'entity')
            ->assertJsonPath('data.attributes.identifier_masked', '2011/······/07')
            ->assertJsonPath('included.0.type', 'client-contact-persons')
            ->assertJsonPath('included.0.attributes.name', 'Lerato Dube')
            ->assertJsonPath('included.0.attributes.mobile_masked', '083 ··· 2222')
            ->assertJsonPath('included.0.attributes.email_masked', 'le····@example.com');
    }

    public function test_a_date_of_birth_is_never_shown_only_that_there_is_one(): void
    {
        $this->signInAs(Role::CandidateAttorney);
        $client = Client::factory()->foreign()->create(['date_of_birth' => '1990-01-01']);

        $response = $this->getJson("/api/v1/clients/{$client->id}")
            ->assertOk()
            ->assertJsonPath('data.attributes.identity_type', IdentityType::Passport->value)
            ->assertJsonPath('data.attributes.date_of_birth_masked', '·· ··· ····');

        $this->assertStringNotContainsString('1990', $response->getContent());
    }

    public function test_without_view_client_contacts_no_contacts_come_even_when_asked_for(): void
    {
        $this->signInAs(Role::Manager);
        $client = Client::factory()->create();
        ClientContact::factory()->for($client)->create();

        $this->getJson("/api/v1/clients/{$client->id}?include=contacts")
            ->assertOk()
            ->assertJsonMissingPath('included')
            ->assertJsonMissingPath('data.relationships')
            ->assertJsonPath('data.meta.can', ['view_contacts' => false, 'reveal' => false]);
    }

    public function test_a_client_the_user_may_not_see_is_not_found(): void
    {
        $this->signInAs(Role::Messenger);
        $client = Client::factory()->create();

        $this->getJson("/api/v1/clients/{$client->id}")->assertJsonApiError(404);
    }

    public function test_reading_a_client_is_recorded_as_from_the_app(): void
    {
        $user = $this->signInAs(Role::CandidateAttorney);
        $client = Client::factory()->create();

        $this->getJson("/api/v1/clients/{$client->id}")->assertOk();

        $read = Activity::forSubject($client)->where('event', 'read')->sole();

        $this->assertTrue($read->causer->is($user));
        $this->assertSame('api', $read->getProperty('via'));
    }
}
