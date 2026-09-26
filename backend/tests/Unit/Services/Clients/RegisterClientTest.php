<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Clients;

use App\Enums\Client\ClientType;
use App\Models\Client;
use App\Services\Clients\DuplicateClientException;
use App\Services\Clients\GenerateBlindIndex;
use App\Services\Clients\RegisterClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RegisterClientTest extends TestCase
{
    use RefreshDatabase;

    private const ID_NUMBER = '9001015800088';

    public function test_it_registers_an_individual(): void
    {
        $client = $this->registerIndividual();

        $this->assertTrue($client->exists);
        $this->assertSame(ClientType::Individual, $client->type);
        $this->assertSame('Jane Doe', $client->full_name);
        $this->assertSame(self::ID_NUMBER, $client->id_number);
    }

    public function test_it_stores_a_blind_index_for_the_id_number(): void
    {
        $client = $this->registerIndividual();

        $this->assertSame(GenerateBlindIndex::of(self::ID_NUMBER), $client->id_number_hash);
    }

    public function test_it_encrypts_the_id_number_at_rest(): void
    {
        $client = $this->registerIndividual();

        $stored = DB::table('clients')->where('id', $client->id)->value('id_number');

        $this->assertNotSame(self::ID_NUMBER, $stored);
    }

    public function test_it_normalises_a_formatted_id_number_before_storing(): void
    {
        $client = $this->registerIndividual('9001 015800 088');

        $this->assertSame(self::ID_NUMBER, $client->id_number);
        $this->assertSame(GenerateBlindIndex::of(self::ID_NUMBER), $client->id_number_hash);
    }

    public function test_it_finds_a_client_by_id_number_without_decrypting(): void
    {
        $client = $this->registerIndividual();

        $found = Client::matchingIdNumber(self::ID_NUMBER)->first();

        $this->assertTrue($found->is($client));
    }

    /** Fails until the scope normalises its input (see model edits below). */
    public function test_it_finds_a_client_by_a_formatted_id_number(): void
    {
        $client = $this->registerIndividual();

        $found = Client::matchingIdNumber('9001 015800 088')->first();

        $this->assertTrue($found->is($client));
    }

    public function test_it_rejects_a_duplicate_id_number_however_it_is_formatted(): void
    {
        $existing = $this->registerIndividual();

        try {
            $this->registerIndividual('9001 015800 088');
            $this->fail('Expected a DuplicateClientException.');
        } catch (DuplicateClientException $e) {
            $this->assertTrue($e->existing->is($existing));
        }

        $this->assertDatabaseCount('clients', 1);
    }

    public function test_it_rejects_the_id_number_of_a_soft_deleted_client(): void
    {
        $existing = $this->registerIndividual();
        $existing->delete();

        try {
            $this->registerIndividual();
            $this->fail('Expected a DuplicateClientException.');
        } catch (DuplicateClientException $e) {
            $this->assertTrue($e->existing->trashed());
        }
    }

    public function test_it_registers_different_individuals_side_by_side(): void
    {
        $this->registerIndividual();
        $this->registerIndividual('8505055009088');

        $this->assertDatabaseCount('clients', 2);
    }

    public function test_it_registers_an_entity_by_registration_number(): void
    {
        $client = $this->registerEntity();

        $this->assertSame(ClientType::Entity, $client->type);
        $this->assertSame('Acme (Pty) Ltd', $client->full_name);
        $this->assertSame('2020/123456/07', $client->registration_number);
        $this->assertNull($client->id_number);
        $this->assertNull($client->id_number_hash);
    }

    public function test_it_rejects_a_duplicate_registration_number(): void
    {
        $existing = $this->registerEntity();

        try {
            $this->registerEntity('2020 / 123456 / 07');
            $this->fail('Expected a DuplicateClientException.');
        } catch (DuplicateClientException $e) {
            $this->assertTrue($e->existing->is($existing));
        }
    }

    private function registerIndividual(string $idNumber = self::ID_NUMBER): Client
    {
        return app(RegisterClient::class)->handle(ClientType::Individual, [
            'first_name' => ' Jane ',
            'last_name' => 'Doe',
            'id_number' => $idNumber,
        ]);
    }

    private function registerEntity(string $registrationNumber = ' 2020/123456/07 '): Client
    {
        return app(RegisterClient::class)->handle(ClientType::Entity, [
            'entity_name' => ' Acme (Pty) Ltd ',
            'registration_number' => $registrationNumber,
        ]);
    }
}
