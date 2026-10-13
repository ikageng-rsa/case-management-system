<?php

declare(strict_types=1);

namespace Tests\Feature\Client;

use App\Enums\Auth\Role as RoleEnum;
use App\Enums\Client\ContactKind;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ClientContactsTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_the_contacts_tab_lists_a_clients_contacts_masked(): void
    {
        $client = Client::factory()->entity()->create();
        ClientContact::factory()->for($client)->mobile()->primary()->create(['value' => '0721234418']);

        $this->actingAs($this->userWithRole(RoleEnum::Secretary))
            ->get(route('client.show', [$client, 'tab' => 'contacts']))
            ->assertOk()
            ->assertSee('072 ··· 4418')
            ->assertDontSee('0721234418')
            ->assertDontSee('+27721234418')
            ->assertSee('Primary');
    }

    public function test_the_contact_tabs_are_for_users_who_may_see_how_a_client_is_reached(): void
    {
        $client = Client::factory()->create();

        $this->actingAs($this->userWithRole(RoleEnum::Manager))
            ->get(route('client.show', [$client, 'tab' => 'contacts']))
            ->assertOk()
            ->assertViewHas('tab', 'overview')
            ->assertDontSee('Contact persons');
    }

    public function test_a_contact_can_be_added_to_an_entity_in_any_number(): void
    {
        $client = Client::factory()->entity()->create();
        $secretary = $this->userWithRole(RoleEnum::Secretary);

        foreach (['0721234418', '0831112222'] as $number) {
            $this->actingAs($secretary)
                ->post(route('client.contacts.store', $client), ['kind' => 'mobile', 'value' => $number])
                ->assertRedirect(route('client.show', [$client, 'tab' => 'contacts']))
                ->assertSessionHasNoErrors();
        }

        $this->assertCount(2, $client->contacts);
        $this->assertSame(1, $client->contacts()->primary()->count());
    }

    public function test_an_individual_has_one_contact_of_each_kind(): void
    {
        $client = Client::factory()->create();
        ClientContact::factory()->for($client)->mobile()->primary()->create();

        $this->actingAs($this->userWithRole(RoleEnum::Secretary))
            ->post(route('client.contacts.store', $client), ['kind' => 'mobile', 'value' => '0831112222'])
            ->assertSessionHasErrorsIn('contact_new', 'kind');

        $this->assertCount(1, $client->fresh()->contacts);
    }

    public function test_a_contact_is_validated_for_its_kind_and_against_duplicates(): void
    {
        $client = Client::factory()->entity()->create();
        $secretary = $this->userWithRole(RoleEnum::Secretary);

        $this->actingAs($secretary)
            ->post(route('client.contacts.store', $client), ['kind' => 'email', 'value' => 'not-an-email'])
            ->assertSessionHasErrorsIn('contact_new', 'value');

        $this->actingAs($secretary)->post(route('client.contacts.store', $client), ['kind' => 'email', 'value' => 'a@example.com']);

        $this->actingAs($secretary)
            ->post(route('client.contacts.store', $client), ['kind' => 'email', 'value' => 'A@Example.com'])
            ->assertSessionHasErrorsIn('contact_new', 'value');
    }

    public function test_contact_persons_are_not_a_kind_of_contact(): void
    {
        $client = Client::factory()->entity()->create();

        $this->actingAs($this->userWithRole(RoleEnum::Secretary))
            ->post(route('client.contacts.store', $client), ['kind' => 'contact-person', 'value' => 'Someone'])
            ->assertSessionHasErrorsIn('contact_new', 'kind');
    }

    public function test_a_contact_is_updated_and_an_empty_value_keeps_the_current_one(): void
    {
        $client = Client::factory()->entity()->create();
        $contact = ClientContact::factory()->for($client)->mobile()->primary()->create(['value' => '0721234418']);
        $secretary = $this->userWithRole(RoleEnum::Secretary);

        $this->actingAs($secretary)
            ->patch(route('client.contacts.update', [$client, $contact]), ['value' => ''])
            ->assertSessionHasNoErrors();

        $this->assertSame('+27721234418', $contact->fresh()->value);

        $this->actingAs($secretary)
            ->patch(route('client.contacts.update', [$client, $contact]), ['value' => '083 111 2222'])
            ->assertSessionHasNoErrors();

        $this->assertSame('+27831112222', $contact->fresh()->value);
        $this->assertSame(1, ClientContact::query()->where('client_id', $client->getKey())->matchingValue('0831112222', ContactKind::Mobile)->count());
    }

    public function test_a_contact_can_keep_its_own_value_but_not_take_a_siblings(): void
    {
        $client = Client::factory()->entity()->create();
        $one = ClientContact::factory()->for($client)->mobile()->primary()->create(['value' => '0721234418']);
        ClientContact::factory()->for($client)->mobile()->create(['value' => '0831112222']);
        $secretary = $this->userWithRole(RoleEnum::Secretary);

        $this->actingAs($secretary)
            ->patch(route('client.contacts.update', [$client, $one]), ['value' => '0721234418'])
            ->assertSessionHasNoErrors();

        $this->actingAs($secretary)
            ->patch(route('client.contacts.update', [$client, $one]), ['value' => '0831112222'])
            ->assertSessionHasErrorsIn('contact_'.$one->getKey(), 'value');
    }

    public function test_the_primary_of_a_kind_moves_by_naming_another(): void
    {
        $client = Client::factory()->entity()->create();
        $first = ClientContact::factory()->for($client)->mobile()->primary()->create();
        $second = ClientContact::factory()->for($client)->mobile()->create();

        $this->actingAs($this->userWithRole(RoleEnum::Secretary))
            ->patch(route('client.contacts.update', [$client, $second]), ['value' => '', 'is_primary' => '1']);

        $this->assertTrue($second->fresh()->is_primary);
        $this->assertFalse($first->fresh()->is_primary);
    }

    public function test_a_contact_is_removed_outright_and_the_next_of_its_kind_becomes_primary(): void
    {
        $client = Client::factory()->entity()->create();
        $first = ClientContact::factory()->for($client)->mobile()->primary()->create();
        $second = ClientContact::factory()->for($client)->mobile()->create();

        $this->actingAs($this->userWithRole(RoleEnum::Secretary))
            ->delete(route('client.contacts.destroy', [$client, $first]))
            ->assertRedirect(route('client.show', [$client, 'tab' => 'contacts']));

        $this->assertDatabaseMissing('client_contacts', ['id' => $first->getKey()]);
        $this->assertTrue($second->fresh()->is_primary);
    }

    public function test_the_last_remaining_contact_cannot_be_removed(): void
    {
        $client = Client::factory()->create();
        $only = ClientContact::factory()->for($client)->mobile()->primary()->create();

        $this->actingAs($this->userWithRole(RoleEnum::Secretary))
            ->delete(route('client.contacts.destroy', [$client, $only]))
            ->assertSessionHasErrorsIn('contacts', 'contact');

        $this->assertDatabaseHas('client_contacts', ['id' => $only->getKey()]);
    }

    public function test_the_last_contact_rule_counts_every_kind(): void
    {
        $client = Client::factory()->create();
        $mobile = ClientContact::factory()->for($client)->mobile()->primary()->create();
        ClientContact::factory()->for($client)->primary()->create();

        $this->actingAs($this->userWithRole(RoleEnum::Secretary))
            ->delete(route('client.contacts.destroy', [$client, $mobile]))
            ->assertSessionHasNoErrors();

        $this->assertCount(1, $client->fresh()->contacts);
    }

    public function test_editing_needs_the_edit_permission_and_the_right_client(): void
    {
        $client = Client::factory()->entity()->create();
        $contact = ClientContact::factory()->for($client)->mobile()->primary()->create();
        $other = Client::factory()->create();

        $this->actingAs($this->userWithRole(RoleEnum::CandidateAttorney))
            ->patch(route('client.contacts.update', [$client, $contact]), ['value' => '0831112222'])
            ->assertForbidden();

        $this->actingAs($this->userWithRole(RoleEnum::Secretary))
            ->patch(route('client.contacts.update', [$other, $contact]), ['value' => '0831112222'])
            ->assertNotFound();
    }

    public function test_a_change_of_value_is_audited_by_name_and_never_carries_the_value(): void
    {
        $client = Client::factory()->entity()->create();
        $contact = ClientContact::factory()->for($client)->mobile()->primary()->create(['value' => '0721234418']);
        Activity::query()->delete();

        $this->actingAs($this->userWithRole(RoleEnum::Secretary))
            ->patch(route('client.contacts.update', [$client, $contact]), ['value' => '0831112222']);

        // The model keeps the value out of its own log, so the change is recorded as an event of its own.
        $this->assertSame(0, Activity::forSubject($contact)->where('event', 'updated')->count());
        $this->assertSame(1, Activity::forSubject($contact)->where('event', 'value_changed')->count());
        $this->assertSame('mobile', Activity::forSubject($contact)->where('event', 'value_changed')->firstOrFail()->properties['field']);
        $this->assertStringNotContainsString('831112222', Activity::forSubject($contact)->get()->toJson());

        // Saving the same value again is no change, so nothing is recorded.
        Activity::query()->delete();
        $this->actingAs($this->userWithRole(RoleEnum::Secretary))
            ->patch(route('client.contacts.update', [$client, $contact]), ['value' => '0831112222']);
        $this->assertSame(0, Activity::forSubject($contact)->count());
    }
}
