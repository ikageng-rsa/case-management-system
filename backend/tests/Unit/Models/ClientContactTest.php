<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\Client\ContactKind;
use App\Models\Client;
use App\Models\ClientContact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ClientContactTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_encrypts_the_value_at_rest(): void
    {
        $contact = ClientContact::factory()->create(['value' => 'jane@example.com']);

        $stored = DB::table('client_contacts')->where('id', $contact->id)->value('value');

        $this->assertNotSame('jane@example.com', $stored);
        $this->assertSame('jane@example.com', $contact->fresh()->value);
    }

    public function test_it_finds_a_contact_by_value_without_decrypting(): void
    {
        $contact = ClientContact::factory()->create(['value' => 'jane@example.com']);
        ClientContact::factory()->create(['value' => 'someone@example.com']);

        $found = ClientContact::matchingValue('jane@example.com')->get();

        $this->assertCount(1, $found);
        $this->assertTrue($found->first()->is($contact));
    }

    public function test_it_matches_a_value_regardless_of_casing(): void
    {
        ClientContact::factory()->create(['value' => 'jane@example.com']);

        $this->assertCount(1, ClientContact::matchingValue('Jane@Example.COM')->get());
    }

    public function test_it_demotes_the_previous_primary_contact_of_the_same_kind(): void
    {
        $client = Client::factory()->create();

        $first = ClientContact::factory()->for($client)->primary()->create(['value' => 'first@example.com']);
        $second = ClientContact::factory()->for($client)->primary()->create(['value' => 'second@example.com']);

        $this->assertFalse($first->fresh()->is_primary);
        $this->assertTrue($second->fresh()->is_primary);
    }

    public function test_it_keeps_a_primary_contact_per_kind(): void
    {
        $client = Client::factory()->create();

        $email = ClientContact::factory()->for($client)->primary()->create(['value' => 'jane@example.com']);
        $mobile = ClientContact::factory()->for($client)->mobile()->primary()->create();

        $this->assertTrue($email->fresh()->is_primary);
        $this->assertTrue($mobile->fresh()->is_primary);
        $this->assertSame(ContactKind::Mobile, $mobile->kind);
    }

    public function test_it_deletes_contacts_with_their_client(): void
    {
        $client = Client::factory()->create();
        ClientContact::factory()->for($client)->create();

        $client->forceDelete();

        $this->assertSame(0, ClientContact::query()->count());
    }
}
