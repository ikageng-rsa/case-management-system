<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Clients;

use App\Enums\Client\ClientType;
use App\Enums\Client\ContactKind;
use App\Models\Client;
use App\Models\ClientContact;
use App\Services\Clients\AddClientContact;
use App\Services\Clients\GenerateBlindIndex;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AddClientContactTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_normalises_an_email_and_stores_its_blind_index(): void
    {
        $contact = $this->add($this->individual(), ContactKind::Email, '  Jane@Example.COM ');

        $this->assertSame('jane@example.com', $contact->value);
        $this->assertSame(GenerateBlindIndex::of('jane@example.com'), $contact->value_hash);
    }

    #[DataProvider('southAfricanPhoneFormats')]
    public function test_it_stores_phone_numbers_in_e164(string $input): void
    {
        $contact = $this->add($this->individual(), ContactKind::Mobile, $input);

        $this->assertSame('+27821234567', $contact->value);
    }

    /** @return array<string, array{string}> */
    public static function southAfricanPhoneFormats(): array
    {
        return [
            'local with spaces' => ['082 123 4567'],
            'local with brackets and dash' => ['(082) 123-4567'],
            'country code without plus' => ['27821234567'],
            'international with spaces' => ['+27 82 123 4567'],
        ];
    }

    public function test_it_encrypts_the_value_at_rest(): void
    {
        $contact = $this->add($this->individual(), ContactKind::Email, 'jane@example.com');

        $stored = DB::table('client_contacts')->where('id', $contact->id)->value('value');

        $this->assertNotSame('jane@example.com', $stored);
    }

    public function test_it_finds_a_contact_by_value_without_decrypting(): void
    {
        $contact = $this->add($this->individual(), ContactKind::Email, 'jane@example.com');

        $found = ClientContact::matchingValue('  Jane@Example.com ')->first();

        $this->assertTrue($found->is($contact));
    }

    /** Fails until the scope accepts a kind and normalises (see model edits below). */
    public function test_it_finds_a_phone_contact_by_a_formatted_number(): void
    {
        $contact = $this->add($this->individual(), ContactKind::Mobile, '0821234567');

        $found = ClientContact::matchingValue('082 123 4567', ContactKind::Mobile)->first();

        $this->assertTrue($found->is($contact));
    }

    /*public function test_it_rejects_a_malformed_email(): void
    {
        $this->expectException(ValidationException::class);

        $this->add($this->individual(), ContactKind::Email, 'not-an-email');
    }

    public function test_it_rejects_a_malformed_phone_number(): void
    {
        $this->expectException(ValidationException::class);

        $this->add($this->individual(), ContactKind::Mobile, '12345');
    }*/

    public function test_it_rejects_a_duplicate_contact_for_the_same_client(): void
    {
        $client = $this->entity();
        $this->add($client, ContactKind::Email, 'info@acme.test');

        $this->expectException(ValidationException::class);  // ← Expects validation error
        $this->add($client, ContactKind::Email, '  INFO@acme.test ');  // ← Gets DB constraint error
    }

    public function test_different_clients_can_share_a_contact(): void
    {
        $this->add($this->individual(), ContactKind::Email, 'shared@example.com');

        $contact = $this->add($this->entity(), ContactKind::Email, 'shared@example.com');

        $this->assertTrue($contact->exists);
    }

    public function test_the_first_contact_of_a_kind_becomes_primary(): void
    {
        $client = $this->individual();

        $email = $this->add($client, ContactKind::Email, 'jane@example.com');
        $phone = $this->add($client, ContactKind::Mobile, '0821234567');

        $this->assertTrue($email->is_primary);
        $this->assertTrue($phone->is_primary);
    }

    public function test_an_individual_cannot_have_two_contacts_of_the_same_kind(): void
    {
        $client = $this->individual();
        $this->add($client, ContactKind::Email, 'jane@example.com');

        $this->expectException(ValidationException::class);

        $this->add($client, ContactKind::Email, 'jane.doe@example.com');
    }

    public function test_an_entity_can_have_several_contacts_of_the_same_kind(): void
    {
        $client = $this->entity();

        $first = $this->add($client, ContactKind::Email, 'info@acme.test');
        $second = $this->add($client, ContactKind::Email, 'accounts@acme.test');

        $this->assertTrue($first->fresh()->is_primary);
        $this->assertFalse($second->fresh()->is_primary);
        $this->assertSame(2, $client->contacts()->count());
    }

    public function test_promoting_a_contact_demotes_the_previous_primary(): void
    {
        $client = $this->entity();
        $first = $this->add($client, ContactKind::Email, 'info@acme.test');

        $second = $this->add($client, ContactKind::Email, 'accounts@acme.test', isPrimary: true);

        $this->assertFalse($first->fresh()->is_primary);
        $this->assertTrue($second->fresh()->is_primary);
    }

    public function test_it_refreshes_the_clients_loaded_contacts(): void
    {
        $client = $this->individual();
        $this->assertNull($client->primaryContact(ContactKind::Email));   // caches the empty relation

        $contact = $this->add($client, ContactKind::Email, 'jane@example.com');

        $this->assertTrue($client->primaryContact(ContactKind::Email)->is($contact));
    }

    private function add(Client $client, ContactKind $kind, string $value, bool $isPrimary = false): ClientContact
    {
        return app(AddClientContact::class)->add($client, $kind, $value, $isPrimary);
    }

    private function individual(): Client
    {
        return Client::create([
            'type' => ClientType::Individual,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'id_number' => '9001015800088',
        ]);
    }

    private function entity(): Client
    {
        return Client::create([
            'type' => ClientType::Entity,
            'entity_name' => 'Acme (Pty) Ltd',
            'registration_number' => '2020/123456/07',
        ]);
    }
}
