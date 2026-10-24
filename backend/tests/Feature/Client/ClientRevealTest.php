<?php

declare(strict_types=1);

namespace Tests\Feature\Client;

use App\Enums\Auth\Role as RoleEnum;
use App\Models\Client\Client;
use App\Models\Client\ClientContact;
use App\Models\Client\ClientContactPerson;
use App\Models\User\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ClientRevealTest extends TestCase
{
    use RefreshDatabase;

    private const ID_NUMBER = '9001015800088';

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

    public function test_an_identifier_is_revealed_to_a_user_cleared_to_see_it_and_the_reading_is_logged(): void
    {
        $client = Client::factory()->create(['id_number' => self::ID_NUMBER]);
        $user = $this->secretary();
        Activity::query()->delete();

        $this->actingAs($user)
            ->postJson(route('client.reveal', $client), ['target' => 'identifier'])
            ->assertOk()
            ->assertExactJson(['value' => self::ID_NUMBER]);

        $log = Activity::forSubject($client)->where('event', 'revealed')->firstOrFail();

        $this->assertSame('id_number', $log->properties['field']);
        $this->assertTrue($log->causer->is($user));
        $this->assertStringNotContainsString(self::ID_NUMBER, $log->toJson());
    }

    public function test_an_entitys_registration_number_is_revealed_in_place_of_an_id(): void
    {
        $client = Client::factory()->entity()->create(['registration_number' => '2011/123456/07']);

        $this->actingAs($this->secretary())
            ->postJson(route('client.reveal', $client), ['target' => 'identifier'])
            ->assertExactJson(['value' => '2011/123456/07']);
    }

    public function test_contacts_and_persons_are_revealed_and_each_reading_is_logged(): void
    {
        $client = Client::factory()->entity()->create();
        $contact = ClientContact::factory()->for($client)->mobile()->primary()->create(['value' => '0721234418']);
        $person = ClientContactPerson::factory()->for($client)->primary()->create(['email' => 'sipho@example.com']);
        $user = $this->secretary();
        Activity::query()->delete();

        $this->actingAs($user)
            ->postJson(route('client.reveal', $client), ['target' => 'contact:'.$contact->getKey()])
            ->assertExactJson(['value' => '+27721234418']);

        $this->actingAs($user)
            ->postJson(route('client.reveal', $client), ['target' => 'person:'.$person->getKey().':email'])
            ->assertExactJson(['value' => 'sipho@example.com']);

        // Every reveal is its own entry; nothing is folded together.
        $this->actingAs($user)->postJson(route('client.reveal', $client), ['target' => 'contact:'.$contact->getKey()]);

        $this->assertSame(3, Activity::query()->where('event', 'revealed')->count());
        $this->assertSame(2, Activity::forSubject($contact)->where('event', 'revealed')->count());
        $this->assertSame('email', Activity::forSubject($person)->where('event', 'revealed')->firstOrFail()->properties['field']);
        $this->assertStringNotContainsString('721234418', Activity::query()->get()->toJson());
    }

    public function test_revealing_needs_the_unmasked_permission(): void
    {
        $client = Client::factory()->create(['id_number' => self::ID_NUMBER]);

        $reader = User::factory()->create();
        $reader->givePermissionTo('view_clients', 'view_client_contacts');

        $this->actingAs($reader)
            ->postJson(route('client.reveal', $client), ['target' => 'identifier'])
            ->assertForbidden();

        $this->assertDatabaseMissing('activity_log', ['event' => 'revealed']);
    }

    public function test_contacts_need_the_contacts_permission_even_to_a_user_cleared_for_identifiers(): void
    {
        $client = Client::factory()->create();
        $contact = ClientContact::factory()->for($client)->mobile()->primary()->create();

        $user = User::factory()->create();
        $user->givePermissionTo('view_clients', 'view_unmasked_client_data');

        $this->actingAs($user)->postJson(route('client.reveal', $client), ['target' => 'identifier'])->assertOk();
        $this->actingAs($user)->postJson(route('client.reveal', $client), ['target' => 'contact:'.$contact->getKey()])->assertForbidden();
    }

    public function test_a_target_belonging_to_another_client_or_that_makes_no_sense_is_not_found(): void
    {
        $client = Client::factory()->create();
        $other = ClientContact::factory()->mobile()->primary()->create();
        $secretary = $this->secretary();

        $this->actingAs($secretary)->postJson(route('client.reveal', $client), ['target' => 'contact:'.$other->getKey()])->assertNotFound();
        $this->actingAs($secretary)->postJson(route('client.reveal', $client), ['target' => 'person:'.fake()->uuid().':email'])->assertNotFound();
        $this->actingAs($secretary)->postJson(route('client.reveal', $client), ['target' => 'anything-else'])->assertNotFound();
        $this->actingAs($secretary)->postJson(route('client.reveal', $client), [])->assertUnprocessable();
    }

    public function test_the_eye_only_appears_for_users_who_may_use_it(): void
    {
        $client = Client::factory()->create(['id_number' => self::ID_NUMBER]);

        $this->actingAs($this->secretary())
            ->get(route('client.show', $client))
            ->assertSee('x-data="reveal(', false);

        $reader = User::factory()->create();
        $reader->givePermissionTo('view_clients');

        $this->actingAs($reader)
            ->get(route('client.show', $client))
            ->assertDontSee('x-data="reveal(', false)
            ->assertSee('·········0088');
    }

    public function test_reveals_show_up_in_the_audit_log_page(): void
    {
        $client = Client::factory()->create(['id_number' => self::ID_NUMBER]);
        $director = User::factory()->create();
        $director->assignRole(RoleEnum::Director->value);

        $this->actingAs($director)->postJson(route('client.reveal', $client), ['target' => 'identifier']);

        $this->actingAs($director)
            ->get(route('audit-log', ['action' => 'revealed']))
            ->assertOk()
            ->assertViewHas('activities', fn ($page) => $page->total() === 1);
    }
}
