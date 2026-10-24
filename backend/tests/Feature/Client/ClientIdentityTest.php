<?php

declare(strict_types=1);

namespace Tests\Feature\Client;

use App\Enums\Auth\Role as RoleEnum;
use App\Enums\Client\IdentityType;
use App\Models\Client\Client;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ClientIdentityTest extends TestCase
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

    private function register(array $overrides = []): TestResponse
    {
        return $this->actingAs($this->userWithRole(RoleEnum::Secretary))
            ->post(route('client.store'), $overrides + [
                'type' => 'individual',
                'first_name' => 'Amara',
                'last_name' => 'Okafor',
            ]);
    }

    public function test_an_individual_can_be_registered_without_any_identity_document(): void
    {
        $this->register()->assertSessionHasNoErrors();

        $client = Client::firstOrFail();

        $this->assertNull($client->id_type);
        $this->assertNull($client->id_number);
        $this->assertNull($client->date_of_birth);
    }

    public function test_an_individual_can_be_registered_with_a_date_of_birth_only(): void
    {
        $this->register(['date_of_birth' => '1985-06-15'])->assertSessionHasNoErrors();

        $this->assertSame('1985-06-15', Client::firstOrFail()->date_of_birth->toDateString());
    }

    public function test_a_passport_is_kept_as_one_and_normalised_as_letters_and_digits(): void
    {
        $this->register(['id_type' => 'passport', 'id_number' => 'a1234 5678', 'date_of_birth' => '1985-06-15'])
            ->assertSessionHasNoErrors();

        $client = Client::firstOrFail();

        $this->assertSame(IdentityType::Passport, $client->id_type);
        $this->assertSame('A12345678', $client->id_number);
    }

    public function test_a_passport_number_is_not_checked_as_a_south_african_id(): void
    {
        $this->register(['id_type' => 'sa_id', 'id_number' => 'A12345678'])->assertSessionHasErrorsIn('client', 'id_number');
        $this->register(['id_type' => 'passport', 'id_number' => 'A12345678'])->assertSessionHasNoErrors();
    }

    public function test_a_south_african_id_brings_its_date_of_birth_and_a_typed_one_has_to_agree(): void
    {
        $this->register(['id_type' => 'sa_id', 'id_number' => self::ID_NUMBER, 'date_of_birth' => '1991-01-01'])
            ->assertSessionHasErrorsIn('client', 'date_of_birth');

        $this->register(['id_type' => 'sa_id', 'id_number' => self::ID_NUMBER])->assertSessionHasNoErrors();

        $this->assertSame('1990-01-01', Client::firstOrFail()->date_of_birth->toDateString());
    }

    public function test_the_same_number_on_different_kinds_of_document_does_not_clash(): void
    {
        Client::factory()->create(['id_type' => IdentityType::Passport, 'id_number' => 'A12345678']);

        $this->register(['id_type' => 'passport', 'id_number' => 'A12345678'])->assertSessionHasErrorsIn('client', 'id_number');
        $this->register(['id_type' => 'other', 'id_number' => 'A12345678'])->assertSessionHasNoErrors();
    }

    public function test_the_date_of_birth_is_encrypted_at_rest(): void
    {
        $this->register(['date_of_birth' => '1985-06-15']);

        $stored = DB::table('clients')->value('date_of_birth');

        $this->assertNotNull($stored);
        $this->assertStringNotContainsString('1985', $stored);
    }

    public function test_a_number_and_a_date_of_birth_can_be_added_later(): void
    {
        $client = Client::factory()->create(['id_type' => null, 'id_number' => null]);

        $this->actingAs($this->userWithRole(RoleEnum::Secretary))
            ->patch(route('client.update', $client), [
                'first_name' => $client->first_name,
                'last_name' => $client->last_name,
                'id_type' => 'passport',
                'id_number' => 'X9876543',
                'date_of_birth' => '1979-02-03',
            ])
            ->assertSessionHasNoErrors();

        $client->refresh();

        $this->assertSame(IdentityType::Passport, $client->id_type);
        $this->assertSame('X9876543', $client->id_number);
        $this->assertSame('1979-02-03', $client->date_of_birth->toDateString());
    }

    public function test_a_passport_is_named_in_the_header_and_the_date_of_birth_is_masked_until_revealed(): void
    {
        $client = Client::factory()->foreign()->create(['id_number' => 'A12345678', 'date_of_birth' => '1985-06-15']);

        $director = $this->userWithRole(RoleEnum::Director);

        $this->actingAs($director)
            ->get(route('client.show', $client))
            ->assertSee('passport ·····5678')
            ->assertDontSee('15 Jun 1985');

        $this->actingAs($director)
            ->postJson(route('client.reveal', $client), ['target' => 'date_of_birth'])
            ->assertOk()
            ->assertJson(['value' => '15 Jun 1985']);

        $this->assertDatabaseHas('activity_log', ['event' => 'revealed', 'subject_id' => $client->id]);
    }

    public function test_existing_south_african_ids_still_match_after_the_change(): void
    {
        $client = Client::factory()->create(['id_type' => null, 'id_number' => self::ID_NUMBER]);

        $this->assertTrue(Client::matchingIdNumber(self::ID_NUMBER)->first()->is($client));
        $this->assertTrue(Client::matchingIdNumber(self::ID_NUMBER, IdentityType::SouthAfricanId)->first()->is($client));
    }
}
