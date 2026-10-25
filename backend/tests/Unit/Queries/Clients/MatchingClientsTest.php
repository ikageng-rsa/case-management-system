<?php

declare(strict_types=1);

namespace Tests\Unit\Queries\Clients;

use App\Models\Client\Client;
use App\Models\Client\ClientContact;
use App\Queries\Clients\MatchingClients;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatchingClientsTest extends TestCase
{
    use RefreshDatabase;

    public function test_nothing_typed_finds_everyone_in_name_order(): void
    {
        Client::factory()->create(['first_name' => 'Thandi', 'last_name' => 'Zulu']);
        Client::factory()->entity()->create(['entity_name' => 'Acacia Holdings']);
        Client::factory()->create(['first_name' => 'Sipho', 'last_name' => 'Mokoena']);

        $names = (new MatchingClients)->for('  ')->get()->map->full_name->all();

        $this->assertSame(['Acacia Holdings', 'Sipho Mokoena', 'Thandi Zulu'], $names);
    }

    public function test_it_finds_by_name(): void
    {
        $thandi = Client::factory()->create(['first_name' => 'Thandi', 'last_name' => 'Zulu']);
        Client::factory()->create(['first_name' => 'Sipho', 'last_name' => 'Mokoena']);

        $this->assertTrue((new MatchingClients)->for('zul')->sole()->is($thandi));
    }

    public function test_it_finds_by_the_whole_id_number_though_it_is_kept_encrypted(): void
    {
        $client = Client::factory()->create(['id_number' => '9001015800088']);
        Client::factory()->create();

        $this->assertTrue((new MatchingClients)->for('9001015800088')->sole()->is($client));
    }

    public function test_it_finds_by_a_mobile_on_file(): void
    {
        $client = Client::factory()->create();
        ClientContact::factory()->mobile()->for($client)->create(['value' => '0821234567']);
        Client::factory()->create();

        $this->assertTrue((new MatchingClients)->for('082 123 4567')->sole()->is($client));
    }

    public function test_like_wildcards_typed_are_taken_literally(): void
    {
        Client::factory()->create(['first_name' => 'Thandi', 'last_name' => 'Zulu']);

        $this->assertSame(0, (new MatchingClients)->for('%')->count());
    }
}
