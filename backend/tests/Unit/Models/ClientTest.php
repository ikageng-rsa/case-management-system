<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\Client\ClientType;
use App\Enums\Client\ContactKind;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\PopiaConsent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_gets_a_uuid_primary_key(): void
    {
        $client = Client::factory()->create();

        $this->assertTrue(Str::isUuid($client->id));
    }

    public function test_it_encrypts_the_id_number_at_rest(): void
    {
        $client = Client::factory()->create(['id_number' => '9001015800083']);

        $stored = DB::table('clients')->where('id', $client->id)->value('id_number');

        $this->assertNotSame('9001015800083', $stored);
        $this->assertSame('9001015800083', $client->fresh()->id_number);
    }

    public function test_it_finds_a_client_by_id_number_without_decrypting(): void
    {
        $client = Client::factory()->create(['id_number' => '9001015800083']);
        Client::factory()->create(['id_number' => '9001015800084']);

        $found = Client::matchingIdNumber('9001015800083')->get();

        $this->assertCount(1, $found);
        $this->assertTrue($found->first()->is($client));
    }

    public function test_it_rehashes_the_id_number_when_it_changes(): void
    {
        $client = Client::factory()->create(['id_number' => '9001015800083']);

        $client->update(['id_number' => '9001015800084']);

        $this->assertCount(0, Client::matchingIdNumber('9001015800083')->get());
        $this->assertCount(1, Client::matchingIdNumber('9001015800084')->get());
    }

    public function test_it_builds_a_full_name_for_an_individual(): void
    {
        $client = Client::factory()->create([
            'first_name' => 'Jane',
            'last_name' => 'Dlamini',
        ]);

        $this->assertSame('Jane Dlamini', $client->full_name);
    }

    public function test_it_uses_the_registered_name_for_an_entity(): void
    {
        $client = Client::factory()->entity()->create(['entity_name' => 'Acme Holdings']);

        $this->assertSame('Acme Holdings', $client->full_name);
        $this->assertTrue($client->isEntity());
        $this->assertSame(ClientType::Entity, $client->type);
    }

    public function test_it_hides_personal_information_when_serialised(): void
    {
        $client = Client::factory()->create(['id_number' => '9001015800083']);

        $serialised = $client->toArray();

        $this->assertArrayNotHasKey('id_number', $serialised);
        $this->assertArrayNotHasKey('id_number_hash', $serialised);
    }

    public function test_it_reports_popia_consent_from_the_latest_decision(): void
    {
        $client = Client::factory()->create();

        PopiaConsent::factory()->for($client)->create(['granted' => true]);

        $this->assertTrue($client->hasGrantedPopiaConsent());
    }

    public function test_it_reports_no_popia_consent_once_withdrawn(): void
    {
        $client = Client::factory()->create();

        PopiaConsent::factory()->for($client)->withdrawn()->create();

        $this->assertFalse($client->hasGrantedPopiaConsent());
    }

    public function test_it_reports_no_popia_consent_when_none_was_recorded(): void
    {
        $client = Client::factory()->create();

        $this->assertFalse($client->hasGrantedPopiaConsent());
    }

    public function test_it_returns_the_primary_contact_for_a_kind(): void
    {
        $client = Client::factory()->create();

        ClientContact::factory()->for($client)->create(['value' => 'other@example.com']);
        $primary = ClientContact::factory()->for($client)->primary()->create(['value' => 'main@example.com']);

        $this->assertTrue($client->load('contacts')->primaryContact(ContactKind::Email)->is($primary));
    }

    public function test_it_derives_initials_from_every_name_for_an_individual(): void
    {
        $client = Client::factory()->create([
            'first_name' => 'Khalil Moses',
            'last_name' => 'Diale',
        ]);

        $this->assertSame('KMD', $client->initials);
    }

    public function test_it_derives_initials_from_the_registered_name_for_an_entity(): void
    {
        $client = Client::factory()->entity()->create(['entity_name' => 'Thabo Balley']);

        $this->assertSame('TB', $client->initials);
    }
}
