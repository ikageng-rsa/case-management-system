<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Client;

use App\Enums\Auth\Role;
use App\Models\Client\Client;
use App\Models\Client\ClientContact;
use App\Models\Client\ClientContactPerson;
use App\Models\User\User;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;
use Tests\Feature\Api\ApiTestCase;

class RevealClientFieldTest extends ApiTestCase
{
    private const ID_NUMBER = '9001015800088';

    public function test_revealing_needs_a_token(): void
    {
        $client = Client::factory()->create();

        $this->postJson("/api/v1/clients/{$client->id}/reveal", ['target' => 'identifier'])->assertJsonApiError(401);
    }

    public function test_an_identifier_is_revealed_and_the_reading_is_logged_as_from_the_app(): void
    {
        $user = $this->signInAs(Role::CandidateAttorney);
        $client = Client::factory()->create(['id_number' => self::ID_NUMBER]);
        Activity::query()->delete();

        $this->postJson("/api/v1/clients/{$client->id}/reveal", ['target' => 'identifier'])
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('data.type', 'revealed-fields')
            ->assertJsonPath('data.id', 'identifier')
            ->assertJsonPath('data.attributes.field', 'id_number')
            ->assertJsonPath('data.attributes.value', self::ID_NUMBER);

        $log = Activity::forSubject($client)->where('event', 'revealed')->sole();

        $this->assertTrue($log->causer->is($user));
        $this->assertSame('id_number', $log->getProperty('field'));
        $this->assertSame('api', $log->getProperty('via'));
        $this->assertStringNotContainsString(self::ID_NUMBER, $log->toJson());
    }

    public function test_an_entitys_registration_number_is_revealed_in_place_of_an_id(): void
    {
        $this->signInAs(Role::CandidateAttorney);
        $client = Client::factory()->entity()->create(['registration_number' => '2011/123456/07']);

        $this->postJson("/api/v1/clients/{$client->id}/reveal", ['target' => 'identifier'])
            ->assertOk()
            ->assertJsonPath('data.attributes.field', 'registration_number')
            ->assertJsonPath('data.attributes.value', '2011/123456/07');
    }

    public function test_a_date_of_birth_is_revealed(): void
    {
        $this->signInAs(Role::CandidateAttorney);
        $client = Client::factory()->foreign()->create(['date_of_birth' => '1990-01-01']);

        $this->postJson("/api/v1/clients/{$client->id}/reveal", ['target' => 'date_of_birth'])
            ->assertOk()
            ->assertJsonPath('data.attributes.value', '01 Jan 1990');
    }

    public function test_contacts_and_contact_people_are_revealed_and_logged_against_themselves(): void
    {
        $this->signInAs(Role::CandidateAttorney);
        $client = Client::factory()->entity()->create();
        $contact = ClientContact::factory()->for($client)->mobile()->primary()->create(['value' => '0721234418']);
        $person = ClientContactPerson::factory()->for($client)->primary()->create(['email' => 'sipho@example.com']);
        Activity::query()->delete();

        $this->postJson("/api/v1/clients/{$client->id}/reveal", ['target' => "contact:{$contact->id}"])
            ->assertOk()
            ->assertJsonPath('data.attributes.field', 'mobile')
            ->assertJsonPath('data.attributes.value', '+27721234418');

        $this->postJson("/api/v1/clients/{$client->id}/reveal", ['target' => "person:{$person->id}:email"])
            ->assertOk()
            ->assertJsonPath('data.attributes.value', 'sipho@example.com');

        $this->assertSame(1, Activity::forSubject($contact)->where('event', 'revealed')->count());
        $this->assertSame('email', Activity::forSubject($person)->where('event', 'revealed')->sole()->getProperty('field'));
    }

    public function test_revealing_needs_the_unmasked_permission(): void
    {
        $this->signInAs(Role::Manager);
        $client = Client::factory()->create(['id_number' => self::ID_NUMBER]);

        $this->postJson("/api/v1/clients/{$client->id}/reveal", ['target' => 'identifier'])->assertJsonApiError(403);

        $this->assertDatabaseMissing('activity_log', ['event' => 'revealed']);
    }

    public function test_contacts_need_the_contacts_permission_even_to_a_user_cleared_for_identifiers(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view_clients', 'view_unmasked_client_data');
        Sanctum::actingAs($user);
        $client = Client::factory()->create();
        $contact = ClientContact::factory()->for($client)->mobile()->primary()->create();

        $this->postJson("/api/v1/clients/{$client->id}/reveal", ['target' => 'identifier'])->assertOk();
        $this->postJson("/api/v1/clients/{$client->id}/reveal", ['target' => "contact:{$contact->id}"])->assertJsonApiError(403);
    }

    public function test_a_client_the_user_may_not_see_is_not_found(): void
    {
        $this->signInAs(Role::Messenger);
        $client = Client::factory()->create(['id_number' => self::ID_NUMBER]);

        $this->postJson("/api/v1/clients/{$client->id}/reveal", ['target' => 'identifier'])->assertJsonApiError(404);
    }

    public function test_a_target_that_is_missing_belongs_elsewhere_or_makes_no_sense_is_refused(): void
    {
        $this->signInAs(Role::CandidateAttorney);
        $client = Client::factory()->create(['id_number' => null]);
        $other = ClientContact::factory()->mobile()->primary()->create();

        $this->postJson("/api/v1/clients/{$client->id}/reveal", [])->assertJsonApiError(422, '/target');
        $this->postJson("/api/v1/clients/{$client->id}/reveal", ['target' => 'identifier'])->assertJsonApiError(404);
        $this->postJson("/api/v1/clients/{$client->id}/reveal", ['target' => "contact:{$other->id}"])->assertJsonApiError(404);
        $this->postJson("/api/v1/clients/{$client->id}/reveal", ['target' => 'person:'.fake()->uuid().':email'])->assertJsonApiError(404);
        $this->postJson("/api/v1/clients/{$client->id}/reveal", ['target' => 'anything-else'])->assertJsonApiError(404);
    }
}
