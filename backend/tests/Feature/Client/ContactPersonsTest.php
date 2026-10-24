<?php

declare(strict_types=1);

namespace Tests\Feature\Client;

use App\Enums\Auth\Role as RoleEnum;
use App\Models\Client\Client;
use App\Models\Client\ClientContactPerson;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ContactPersonsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RoleSeeder::class);
    }

    protected function secretary(): User
    {
        $user = User::factory()->create();
        $user->assignRole(RoleEnum::Secretary->value);

        return $user;
    }

    public function test_the_tab_lists_the_people_with_their_details_masked(): void
    {
        $client = Client::factory()->entity()->create();
        ClientContactPerson::factory()->for($client)->primary()->create([
            'name' => 'Sipho Dlamini', 'role' => 'Managing director', 'mobile' => '0721234418', 'email' => 'sipho@example.com',
        ]);
        ClientContactPerson::factory()->for($client)->create(['name' => 'Anele Khoza']);

        $this->actingAs($this->secretary())
            ->get(route('client.show', [$client, 'tab' => 'persons']))
            ->assertOk()
            ->assertSeeInOrder(['Sipho Dlamini', 'Managing director', 'First contact', 'Anele Khoza', 'Alternative'])
            ->assertSee('072 ··· 4418')
            ->assertSee('si···@example.com')
            ->assertDontSee('0721234418')
            ->assertDontSee('sipho@example.com');
    }

    public function test_the_first_person_is_the_first_contact_and_later_ones_are_alternatives(): void
    {
        $client = Client::factory()->entity()->create();
        $secretary = $this->secretary();

        $this->actingAs($secretary)
            ->post(route('client.persons.store', $client), ['name' => 'Sipho Dlamini', 'role' => 'Director', 'mobile' => '0721234418'])
            ->assertRedirect(route('client.show', [$client, 'tab' => 'persons']))
            ->assertSessionHasNoErrors();

        $this->actingAs($secretary)
            ->post(route('client.persons.store', $client), ['name' => 'Anele Khoza', 'email' => 'anele@example.com']);

        $people = $client->contactPersons()->orderBy('created_at')->orderBy('id')->get();

        $this->assertTrue($people[0]->is_primary);
        $this->assertFalse($people[1]->is_primary);
    }

    public function test_naming_someone_the_first_contact_moves_the_flag(): void
    {
        $client = Client::factory()->entity()->create();
        $first = ClientContactPerson::factory()->for($client)->primary()->create();

        $this->actingAs($this->secretary())
            ->post(route('client.persons.store', $client), ['name' => 'Anele Khoza', 'email' => 'anele@example.com', 'is_primary' => '1']);

        $this->assertFalse($first->fresh()->is_primary);
        $this->assertSame('Anele Khoza', $client->contactPersons()->where('is_primary', true)->firstOrFail()->name);
        $this->assertSame(1, $client->contactPersons()->where('is_primary', true)->count());
    }

    public function test_a_person_needs_a_name_and_a_way_to_be_reached(): void
    {
        $client = Client::factory()->entity()->create();

        $this->actingAs($this->secretary())
            ->post(route('client.persons.store', $client), ['role' => 'Director'])
            ->assertSessionHasErrorsIn('person_new', ['name', 'mobile']);

        $this->assertCount(0, $client->contactPersons);
    }

    public function test_a_persons_numbers_are_validated_and_stored_encrypted(): void
    {
        $client = Client::factory()->entity()->create();
        $secretary = $this->secretary();

        $this->actingAs($secretary)
            ->post(route('client.persons.store', $client), ['name' => 'Sipho', 'mobile' => '123', 'email' => 'nope'])
            ->assertSessionHasErrorsIn('person_new', ['mobile', 'email']);

        $this->actingAs($secretary)
            ->post(route('client.persons.store', $client), ['name' => 'Sipho', 'mobile' => '072 123 4418', 'email' => 'Sipho@Example.com']);

        $person = $client->contactPersons()->firstOrFail();

        $this->assertSame('+27721234418', $person->mobile);
        $this->assertSame('sipho@example.com', $person->email);

        $raw = DB::table('client_contact_persons')->first();
        $this->assertStringNotContainsString('721234418', $raw->mobile);
        $this->assertStringNotContainsString('sipho', $raw->email);
    }

    public function test_a_person_is_updated_and_empty_numbers_keep_what_is_on_file(): void
    {
        $client = Client::factory()->entity()->create();
        $person = ClientContactPerson::factory()->for($client)->primary()->create(['mobile' => '0721234418', 'email' => 'sipho@example.com']);

        $this->actingAs($this->secretary())
            ->patch(route('client.persons.update', [$client, $person]), ['name' => 'Sipho D', 'role' => 'CEO', 'mobile' => '', 'email' => ''])
            ->assertSessionHasNoErrors();

        $person->refresh();

        $this->assertSame('Sipho D', $person->name);
        $this->assertSame('CEO', $person->role);
        $this->assertSame('+27721234418', $person->mobile);
        $this->assertSame('sipho@example.com', $person->email);
        $this->assertTrue($person->is_primary, 'the first contact flag is not switched off by editing');
    }

    public function test_a_person_cannot_be_left_unreachable(): void
    {
        $client = Client::factory()->entity()->create();
        $person = ClientContactPerson::factory()->for($client)->create(['mobile' => null, 'email' => null]);

        $this->actingAs($this->secretary())
            ->patch(route('client.persons.update', [$client, $person]), ['name' => 'Sipho'])
            ->assertSessionHasErrorsIn('person_'.$person->getKey(), 'mobile');
    }

    public function test_a_person_is_removed_outright_and_the_next_becomes_the_first_contact(): void
    {
        $client = Client::factory()->entity()->create();
        $first = ClientContactPerson::factory()->for($client)->primary()->create();
        $second = ClientContactPerson::factory()->for($client)->create();

        $this->actingAs($this->secretary())
            ->delete(route('client.persons.destroy', [$client, $first]))
            ->assertRedirect(route('client.show', [$client, 'tab' => 'persons']));

        $this->assertDatabaseMissing('client_contact_persons', ['id' => $first->getKey()]);
        $this->assertTrue($second->fresh()->is_primary);
    }

    public function test_managing_people_needs_the_edit_permission_and_the_right_client(): void
    {
        $client = Client::factory()->entity()->create();
        $person = ClientContactPerson::factory()->for($client)->create();

        $candidate = User::factory()->create();
        $candidate->assignRole(RoleEnum::CandidateAttorney->value);

        $this->actingAs($candidate)->post(route('client.persons.store', $client), ['name' => 'X', 'email' => 'x@example.com'])->assertForbidden();
        $this->actingAs($candidate)->delete(route('client.persons.destroy', [$client, $person]))->assertForbidden();

        $this->actingAs($this->secretary())
            ->delete(route('client.persons.destroy', [Client::factory()->create(), $person]))
            ->assertNotFound();
    }

    public function test_changes_are_audited_once_each_and_never_carry_the_numbers(): void
    {
        $client = Client::factory()->entity()->create();
        $person = ClientContactPerson::factory()->for($client)->primary()->create(['mobile' => '0721234418']);
        Activity::query()->delete();

        $this->actingAs($this->secretary())
            ->patch(route('client.persons.update', [$client, $person]), ['name' => 'Renamed', 'mobile' => '0831112222']);

        // Each concern is recorded once: the name by the model, the number as an event that never carries it.
        $this->assertSame(1, Activity::forSubject($person)->where('event', 'updated')->count());
        $this->assertSame(1, Activity::forSubject($person)->where('event', 'value_changed')->count());
        $this->assertSame('mobile', Activity::forSubject($person)->where('event', 'value_changed')->firstOrFail()->properties['field']);
        $this->assertStringNotContainsString('831112222', Activity::forSubject($person)->get()->toJson());
    }
}
