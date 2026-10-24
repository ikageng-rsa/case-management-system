<?php

declare(strict_types=1);

namespace Tests\Feature\Client;

use App\Enums\Auth\Role as RoleEnum;
use App\Enums\Client\ContactKind;
use App\Enums\Client\PopiaConsentMethod;
use App\Enums\Matter\Assignment;
use App\Models\Client;
use App\Models\ClientContactPerson;
use App\Models\Matter\Matter;
use App\Models\Matter\MatterAssignment;
use App\Models\MatterType;
use App\Models\User;
use App\Services\Clients\AddClientContact;
use App\Services\Clients\MaskIdentifier;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ClientControllerTest extends TestCase
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

    public function test_the_page_is_gated_behind_view_clients(): void
    {
        $client = Client::factory()->create();

        $messenger = $this->userWithRole(RoleEnum::Messenger);

        $this->actingAs($messenger)->get(route('clients'))->assertForbidden();
        $this->actingAs($messenger)->get(route('client.show', $client))->assertForbidden();
    }

    public function test_the_index_lands_on_the_first_client(): void
    {
        Client::factory()->create(['first_name' => 'Zola', 'last_name' => 'Zulu']);
        $first = Client::factory()->create(['first_name' => 'Abel', 'last_name' => 'Adams']);

        $this->actingAs($this->userWithRole(RoleEnum::Secretary))
            ->get(route('clients'))
            ->assertRedirect(route('client.show', $first));
    }

    public function test_the_index_renders_empty_when_there_are_no_clients(): void
    {
        $this->actingAs($this->userWithRole(RoleEnum::Secretary))
            ->get(route('clients'))
            ->assertOk()
            ->assertSee('No clients to show.');
    }

    public function test_the_list_can_be_searched_by_name_and_by_whole_identifier(): void
    {
        $abel = Client::factory()->create(['first_name' => 'Abel', 'last_name' => 'Adams', 'id_number' => self::ID_NUMBER]);
        $zola = Client::factory()->create(['first_name' => 'Zola', 'last_name' => 'Zulu']);
        $entity = Client::factory()->entity()->create(['entity_name' => 'Rustenburg Plat', 'registration_number' => '2011/123456/07']);

        $secretary = $this->userWithRole(RoleEnum::Secretary);

        $this->actingAs($secretary)->get(route('client.show', [$zola, 'q' => 'zul']))
            ->assertViewHas('clients', fn ($clients) => $clients->pluck('id')->all() === [$zola->id]);

        $this->actingAs($secretary)->get(route('client.show', [$zola, 'q' => '9001 015800 088']))
            ->assertViewHas('clients', fn ($clients) => $clients->pluck('id')->all() === [$abel->id]);

        $this->actingAs($secretary)->get(route('client.show', [$zola, 'q' => '2011/123456/07']))
            ->assertViewHas('clients', fn ($clients) => $clients->pluck('id')->all() === [$entity->id]);

        // Text that cannot be an ID number must not match every entity through a NULL hash.
        $this->actingAs($secretary)->get(route('client.show', [$zola, 'q' => 'nobody']))
            ->assertViewHas('clients', fn ($clients) => $clients->isEmpty());
    }

    public function test_the_list_can_be_searched_by_phone_number(): void
    {
        $client = Client::factory()->create();
        Client::factory()->create();
        app(AddClientContact::class)->add($client, ContactKind::Mobile, '072 123 4418');

        $this->actingAs($this->userWithRole(RoleEnum::Secretary))
            ->get(route('client.show', [$client, 'q' => '0721234418']))
            ->assertViewHas('clients', fn ($clients) => $clients->pluck('id')->all() === [$client->id]);
    }

    public function test_the_detail_shows_the_clients_matters_and_open_count(): void
    {
        $client = Client::factory()->create();
        Matter::factory()->for($client)->create(['reference' => 'IOT/CIV/1/2026']);
        Matter::factory()->for($client)->closed()->create(['reference' => 'IOT/CIV/2/2026']);
        Matter::factory()->create(['reference' => 'ELSE/CIV/3/2026']);

        $this->actingAs($this->userWithRole(RoleEnum::Director))
            ->get(route('client.show', $client))
            ->assertOk()
            ->assertSee('IOT/CIV/1/2026')
            ->assertSee('IOT/CIV/2/2026')
            ->assertDontSee('ELSE/CIV/3/2026')
            ->assertViewHas('openMattersCount', 1);
    }

    public function test_a_user_only_sees_the_clients_matters_they_carry_unless_they_view_all(): void
    {
        $client = Client::factory()->create();
        $mine = Matter::factory()->for($client)->create(['reference' => 'IOT/CIV/1/2026']);
        Matter::factory()->for($client)->create(['reference' => 'IOT/CIV/2/2026']);

        $attorney = $this->userWithRole(RoleEnum::Attorney);
        MatterAssignment::factory()->for($mine)->for($attorney)->capacity(Assignment::Responsible)->create();

        $this->actingAs($attorney)
            ->get(route('client.show', $client))
            ->assertSee('IOT/CIV/1/2026')
            ->assertDontSee('IOT/CIV/2/2026');
    }

    public function test_identifiers_and_contact_details_are_shown_masked_in_the_list_and_header(): void
    {
        $client = Client::factory()->create(['id_number' => self::ID_NUMBER]);
        app(AddClientContact::class)->add($client, ContactKind::Mobile, '0721234418');
        app(AddClientContact::class)->add($client, ContactKind::Email, 'thabo@example.com');

        // Without the permission to see identifiers in full, nothing in full is on the page.
        $reader = User::factory()->create();
        $reader->givePermissionTo('view_clients', 'view_client_contacts');

        $this->actingAs($reader)
            ->get(route('client.show', $client))
            ->assertSee('072 ··· 4418')
            ->assertSee('th···@example.com')
            ->assertSee('·········0088')
            ->assertDontSee('0721234418')
            ->assertDontSee('+27721234418')
            ->assertDontSee('thabo@example.com')
            ->assertDontSee(self::ID_NUMBER);
    }

    public function test_contact_details_are_withheld_without_the_contacts_permission(): void
    {
        $client = Client::factory()->create();
        app(AddClientContact::class)->add($client, ContactKind::Email, 'thabo@example.com');

        $this->actingAs($this->userWithRole(RoleEnum::Manager))
            ->get(route('client.show', $client))
            ->assertOk()
            ->assertDontSee('thabo@')
            ->assertDontSee('····.com');
    }

    public function test_opening_a_client_is_recorded_in_the_audit_log(): void
    {
        $client = Client::factory()->create();

        $this->actingAs($this->userWithRole(RoleEnum::Secretary))->get(route('client.show', $client));

        $this->assertDatabaseHas('activity_log', ['event' => 'read', 'subject_id' => $client->id]);
    }

    public function test_an_individual_can_be_registered_with_contacts(): void
    {
        $this->actingAs($this->userWithRole(RoleEnum::Secretary))
            ->post(route('client.store'), [
                'type' => 'individual',
                'first_name' => 'Thabo',
                'last_name' => 'Mokoena',
                'id_number' => self::ID_NUMBER,
                'mobile' => '072 123 4418',
                'email' => 'thabo@example.com',
                'physical_address' => '1142 Boitekong Ext 4, Rustenburg, 0300',
                'popia_consent' => '1',
                'popia_method' => 'verbal_witnessed',
            ])
            ->assertSessionHasNoErrors();

        $client = Client::firstOrFail();

        $this->assertSame('Thabo Mokoena', $client->full_name);
        $this->assertCount(3, $client->contacts);
        $this->assertSame('1142 Boitekong Ext 4, Rustenburg, 0300', $client->primaryContact(ContactKind::Physical)->value);
    }

    public function test_an_entity_can_be_registered_without_contacts(): void
    {
        $this->actingAs($this->userWithRole(RoleEnum::Secretary))
            ->post(route('client.store'), [
                'type' => 'entity',
                'entity_name' => 'Rustenburg Plat (Pty) Ltd',
                'registration_number' => '2011/123456/07',
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Client::firstOrFail()->isEntity());
    }

    public function test_a_duplicate_client_is_refused_and_nothing_is_half_saved(): void
    {
        Client::factory()->create(['id_number' => self::ID_NUMBER]);

        $this->actingAs($this->userWithRole(RoleEnum::Secretary))
            ->post(route('client.store'), [
                'type' => 'individual',
                'first_name' => 'Thabo',
                'last_name' => 'Mokoena',
                'id_number' => self::ID_NUMBER,
            ])
            ->assertSessionHasErrorsIn('client');

        $this->assertDatabaseCount('clients', 1);
    }

    public function test_registering_needs_the_create_permission(): void
    {
        $this->actingAs($this->userWithRole(RoleEnum::Manager))
            ->post(route('client.store'), ['type' => 'entity', 'entity_name' => 'X', 'registration_number' => '2011/123456/07'])
            ->assertForbidden();

        $this->assertDatabaseCount('clients', 0);
    }

    public function test_identifiers_are_masked_enough_to_recognise_but_not_reuse(): void
    {
        $this->assertSame('072 ··· 4418', MaskIdentifier::mobile('+27721234418'));
        $this->assertSame('t.·······@example.com', MaskIdentifier::email('t.mokoena@example.com'));
        $this->assertSame('ab·····@example.com', MaskIdentifier::email('abcdefg@example.com'));
        $this->assertSame('a·@example.com', MaskIdentifier::email('ab@example.com'));
        $this->assertSame('·@example.com', MaskIdentifier::email('a@example.com'));
        $this->assertSame('·····', MaskIdentifier::email('hello'));
        $this->assertSame('·········0088', MaskIdentifier::idNumber(self::ID_NUMBER));
        $this->assertSame('····172', MaskIdentifier::idNumber('1596172'));
        $this->assertSame('··45', MaskIdentifier::idNumber('1245'));
        $this->assertSame('·3', MaskIdentifier::idNumber('23'));
        $this->assertSame('·', MaskIdentifier::idNumber('2'));
        $this->assertSame('2011/······/07', MaskIdentifier::registrationNumber('2011/123456/07'));
    }

    public function test_a_client_can_be_edited(): void
    {
        $client = Client::factory()->create(['first_name' => 'Thabo', 'last_name' => 'Mokoena', 'id_number' => self::ID_NUMBER]);

        $this->actingAs($this->userWithRole(RoleEnum::Secretary))
            ->patch(route('client.update', $client), ['first_name' => 'Thabiso', 'last_name' => 'Mokoena', 'id_number' => ''])
            ->assertRedirect(route('client.show', $client))
            ->assertSessionHasNoErrors();

        $client->refresh();

        $this->assertSame('Thabiso', $client->first_name);
        $this->assertSame(self::ID_NUMBER, $client->id_number, 'an empty identifier keeps the current one');
    }

    public function test_an_entity_can_be_edited(): void
    {
        $client = Client::factory()->entity()->create(['entity_name' => 'Old Name', 'registration_number' => '2011/123456/07']);

        $this->actingAs($this->userWithRole(RoleEnum::Secretary))
            ->patch(route('client.update', $client), ['entity_name' => 'New Name', 'registration_number' => '2012/654321/07'])
            ->assertSessionHasNoErrors();

        $this->assertSame('New Name', $client->fresh()->entity_name);
        $this->assertSame('2012/654321/07', $client->fresh()->registration_number);
    }

    public function test_editing_is_recorded_once_by_the_model_and_only_when_something_changed(): void
    {
        $client = Client::factory()->create(['first_name' => 'Thabo', 'last_name' => 'Mokoena', 'id_number' => self::ID_NUMBER]);
        $secretary = $this->userWithRole(RoleEnum::Secretary);

        $before = Activity::forSubject($client)->count();

        $this->actingAs($secretary)
            ->patch(route('client.update', $client), ['first_name' => 'Thabo', 'last_name' => 'Mokoena', 'id_number' => self::ID_NUMBER]);

        $this->assertSame($before, Activity::forSubject($client)->count(), 'saving the same values logs nothing');

        $this->actingAs($secretary)
            ->patch(route('client.update', $client), ['first_name' => 'Thabiso', 'last_name' => 'Mokoena', 'id_number' => '']);

        $this->assertSame(1, Activity::forSubject($client)->where('event', 'updated')->count());
        $this->assertSame(['first_name' => 'Thabiso'], Activity::forSubject($client)->where('event', 'updated')->first()->attribute_changes['attributes']);
    }

    public function test_an_identifier_belonging_to_another_client_is_refused_but_ones_own_is_fine(): void
    {
        $other = Client::factory()->create(['id_number' => '8505055009088']);
        $client = Client::factory()->create(['first_name' => 'Thabo', 'last_name' => 'Mokoena', 'id_number' => self::ID_NUMBER]);
        $secretary = $this->userWithRole(RoleEnum::Secretary);

        $this->actingAs($secretary)
            ->patch(route('client.update', $client), ['first_name' => 'Thabo', 'last_name' => 'Mokoena', 'id_number' => '8505055009088'])
            ->assertSessionHasErrorsIn('client_edit', 'id_number');

        $this->actingAs($secretary)
            ->patch(route('client.update', $client), ['first_name' => 'Thabo', 'last_name' => 'Mokoena', 'id_number' => self::ID_NUMBER])
            ->assertSessionHasNoErrors();

        $this->assertSame($other->id_number, '8505055009088');
    }

    public function test_editing_needs_the_edit_permission(): void
    {
        $client = Client::factory()->create(['first_name' => 'Thabo']);

        $this->actingAs($this->userWithRole(RoleEnum::CandidateAttorney))
            ->patch(route('client.update', $client), ['first_name' => 'X', 'last_name' => 'Y'])
            ->assertForbidden();

        $this->assertSame('Thabo', $client->fresh()->first_name);
    }

    public function test_the_edit_form_never_carries_an_identifier_in_full_whoever_is_looking(): void
    {
        $client = Client::factory()->create(['id_number' => self::ID_NUMBER]);

        // Even someone cleared to read it: reading it is the eye's job, which is checked and logged.
        foreach ([$this->userWithRole(RoleEnum::Secretary), $this->userWithRole(RoleEnum::Director)] as $user) {
            $this->actingAs($user)
                ->get(route('client.show', $client))
                ->assertSee('edit_id_number', false)
                ->assertDontSee(self::ID_NUMBER)
                ->assertSee('·········0088', false);
        }
    }

    public function test_the_entity_edit_form_never_carries_the_registration_number_in_full(): void
    {
        $client = Client::factory()->entity()->create(['registration_number' => '2015/987654/09']);

        $this->actingAs($this->userWithRole(RoleEnum::Director))
            ->get(route('client.show', $client))
            ->assertSee('edit_registration_number', false)
            ->assertDontSee('2015/987654/09')
            ->assertSee('2015/······/09');
    }

    public function test_leaving_the_identifier_empty_in_the_edit_form_keeps_it(): void
    {
        $client = Client::factory()->create(['id_number' => self::ID_NUMBER, 'first_name' => 'Thabo', 'last_name' => 'Mokoena']);

        $this->actingAs($this->userWithRole(RoleEnum::Director))
            ->patch(route('client.update', $client), ['first_name' => 'Thabiso', 'last_name' => 'Mokoena', 'id_number' => ''])
            ->assertSessionHasNoErrors();

        $this->assertSame(self::ID_NUMBER, $client->fresh()->id_number);
    }

    public function test_no_page_about_a_client_prints_their_details_in_full(): void
    {
        $client = Client::factory()->create(['id_number' => self::ID_NUMBER]);
        app(AddClientContact::class)->add($client, ContactKind::Mobile, '0721234418');
        app(AddClientContact::class)->add($client, ContactKind::Email, 'thabo.mokoena@example.com');
        $person = ClientContactPerson::factory()->for($client)->primary()->create(['mobile' => '0831112222', 'email' => 'sipho.dlamini@example.com']);
        $matter = Matter::factory()->for($client)->create();

        $director = $this->userWithRole(RoleEnum::Director);

        $pages = [
            route('client.show', $client),
            route('client.show', [$client, 'tab' => 'contacts']),
            route('client.show', [$client, 'tab' => 'persons']),
            route('matter.show', [$matter, 'tab' => 'parties']),
            route('matter.show', $matter),
        ];

        foreach ($pages as $url) {
            $html = $this->actingAs($director)->get($url)->assertOk()->getContent();

            foreach ([self::ID_NUMBER, '0721234418', '+27721234418', 'thabo.mokoena@example.com', '0831112222', '+27831112222', 'sipho.dlamini@example.com'] as $secret) {
                $this->assertStringNotContainsString($secret, $html, "{$secret} is printed in full on {$url}");
            }
        }
    }

    public function test_the_edit_and_new_matter_actions_follow_their_permissions(): void
    {
        $client = Client::factory()->create();

        $this->actingAs($this->userWithRole(RoleEnum::Secretary))
            ->get(route('client.show', $client))
            ->assertSee('edit-client')
            ->assertSee('new-client-matter');

        $this->actingAs($this->userWithRole(RoleEnum::CandidateAttorney))
            ->get(route('client.show', $client))
            ->assertDontSee('edit-client')
            ->assertDontSee('new-client-matter');
    }

    public function test_a_matter_can_be_opened_for_the_client_from_the_sheet(): void
    {
        $client = Client::factory()->create();
        $type = MatterType::factory()->nonLitigation()->create();

        $this->actingAs($this->userWithRole(RoleEnum::Secretary))
            ->post(route('matter.store'), [
                'client_id' => $client->getKey(),
                'matter_type_id' => $type->getKey(),
                'title' => 'Estate of the late Mr Mokoena',
                'instructed_at' => '2026-10-01',
            ])
            ->assertSessionHasNoErrors();

        $matter = Matter::firstOrFail();

        $this->assertTrue($matter->client->is($client));
        $this->assertSame('Estate of the late Mr Mokoena', $matter->title);
    }

    public function test_a_litigious_matter_needs_a_court(): void
    {
        $client = Client::factory()->create();
        $type = MatterType::factory()->create(['is_litigation' => true]);

        $this->actingAs($this->userWithRole(RoleEnum::Secretary))
            ->post(route('matter.store'), ['client_id' => $client->getKey(), 'matter_type_id' => $type->getKey()])
            ->assertSessionHasErrorsIn('matter', 'court_id');

        $this->assertDatabaseCount('matters', 0);
    }

    public function test_opening_a_matter_needs_the_create_permission(): void
    {
        $client = Client::factory()->create();
        $type = MatterType::factory()->nonLitigation()->create();

        $this->actingAs($this->userWithRole(RoleEnum::Manager))
            ->post(route('matter.store'), ['client_id' => $client->getKey(), 'matter_type_id' => $type->getKey()])
            ->assertForbidden();
    }

    private function individual(array $overrides = []): array
    {
        return $overrides + [
            'type' => 'individual',
            'first_name' => 'Thabo Joseph',
            'last_name' => 'Mokoena',
            'id_number' => self::ID_NUMBER,
            'popia_consent' => '1',
            'popia_method' => 'verbal_witnessed',
        ];
    }

    public function test_registering_records_the_clients_consent_and_how_it_was_given(): void
    {
        $this->actingAs($this->userWithRole(RoleEnum::Secretary))
            ->post(route('client.store'), $this->individual())
            ->assertSessionHasNoErrors();

        $consent = Client::firstOrFail()->popiaConsent;

        $this->assertTrue($consent->isActive());
        $this->assertNotNull($consent->granted_at);
        $this->assertSame(PopiaConsentMethod::Verbal, $consent->method);
        $this->assertNull($consent->mandate());
    }

    public function test_a_client_can_be_registered_without_consent_and_none_is_recorded(): void
    {
        $this->actingAs($this->userWithRole(RoleEnum::Secretary))
            ->post(route('client.store'), $this->individual(['popia_consent' => null, 'popia_method' => null]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('clients', 1);
        $this->assertDatabaseCount('popia_consents', 0);
    }

    public function test_consent_given_at_intake_has_to_say_how(): void
    {
        $this->actingAs($this->userWithRole(RoleEnum::Secretary))
            ->post(route('client.store'), $this->individual(['popia_method' => null]))
            ->assertSessionHasErrorsIn('client', 'popia_method');

        $this->assertDatabaseCount('clients', 0);
    }

    public function test_a_signed_mandate_given_at_intake_has_to_be_attached_and_is_kept_with_the_consent(): void
    {
        Storage::fake(config('media-library.disk_name'));

        $secretary = $this->userWithRole(RoleEnum::Secretary);

        $this->actingAs($secretary)
            ->post(route('client.store'), $this->individual(['popia_method' => 'signed_mandate']))
            ->assertSessionHasErrorsIn('client', 'popia_mandate');

        $this->assertDatabaseCount('clients', 0);

        $this->actingAs($secretary)
            ->post(route('client.store'), $this->individual([
                'popia_method' => 'signed_mandate',
                'popia_mandate' => UploadedFile::fake()->create('mandate.pdf', 100, 'application/pdf'),
            ]))
            ->assertSessionHasNoErrors();

        $consent = Client::firstOrFail()->popiaConsent;

        $this->assertSame(PopiaConsentMethod::Signed, $consent->method);
        $this->assertSame('mandate.pdf', $consent->mandate()?->file_name);
    }

    public function test_saving_and_opening_a_matter_does_both_in_one_go(): void
    {
        $type = MatterType::factory()->nonLitigation()->create();

        $response = $this->actingAs($this->userWithRole(RoleEnum::Secretary))
            ->post(route('client.store'), $this->individual(['matter_type_id' => $type->getKey()]))
            ->assertSessionHasNoErrors();

        $matter = Matter::firstOrFail();

        $response->assertRedirect(route('matter.show', $matter));
        $this->assertTrue($matter->client->is(Client::firstOrFail()));
        $this->assertTrue($matter->matterType->is($type));
    }

    public function test_opening_a_litigious_matter_alongside_needs_a_court(): void
    {
        $type = MatterType::factory()->create(['is_litigation' => true]);

        $this->actingAs($this->userWithRole(RoleEnum::Secretary))
            ->post(route('client.store'), $this->individual(['matter_type_id' => $type->getKey()]))
            ->assertSessionHasErrorsIn('client', 'court_id');

        $this->assertDatabaseCount('clients', 0);
        $this->assertDatabaseCount('matters', 0);
    }

    public function test_no_matter_is_opened_when_no_matter_type_is_chosen(): void
    {
        $this->actingAs($this->userWithRole(RoleEnum::Secretary))
            ->post(route('client.store'), $this->individual(['matter_type_id' => '', 'court_id' => '']))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('client.show', Client::firstOrFail()));

        $this->assertDatabaseCount('clients', 1);
        $this->assertDatabaseCount('matters', 0);
    }

    public function test_opening_a_matter_alongside_needs_the_create_matters_permission(): void
    {
        $type = MatterType::factory()->nonLitigation()->create();

        $user = User::factory()->create();
        $user->givePermissionTo('view_clients', 'create_clients', 'record_popia_consent');

        $this->actingAs($user)
            ->post(route('client.store'), $this->individual(['matter_type_id' => $type->getKey()]))
            ->assertForbidden();

        $this->assertDatabaseCount('clients', 0);
    }

    public function test_the_intake_form_follows_the_users_permissions(): void
    {
        $client = Client::factory()->create();

        $clerk = User::factory()->create();
        $clerk->givePermissionTo('view_clients', 'create_clients');

        $this->actingAs($clerk)
            ->get(route('client.show', $client))
            ->assertSee('Save client')
            ->assertDontSee('intake_matter_type_id')
            ->assertDontSee('intake_popia_consent');

        $this->actingAs($this->userWithRole(RoleEnum::Secretary))
            ->get(route('client.show', $client))
            ->assertSee('Save client')
            ->assertDontSee('Save client only')
            ->assertSee('intake_matter_type_id')
            ->assertSee('intake_popia_consent')
            ->assertSee('intake_popia_method')
            ->assertSee('intake_popia_mandate');
    }
}
