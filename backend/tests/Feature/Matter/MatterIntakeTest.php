<?php

declare(strict_types=1);

namespace Tests\Feature\Matter;

use App\Enums\Auth\Role as RoleEnum;
use App\Models\Client\Client;
use App\Models\Matter\Matter;
use App\Models\Reference\ActivityType;
use App\Models\Reference\MatterType;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatterIntakeTest extends TestCase
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

    protected function payload(array $overrides = []): array
    {
        return $overrides + [
            'client_id' => Client::factory()->create()->getKey(),
            'matter_type_id' => MatterType::factory()->nonLitigation()->create()->getKey(),
            'title' => 'Estate of the late Mr Mokoena',
            'instructed_at' => '2026-10-01',
        ];
    }

    public function test_the_new_matter_form_is_on_the_matters_page_for_users_who_may_open_matters(): void
    {
        $this->actingAs($this->userWithRole(RoleEnum::Secretary))
            ->get(route('matters'))
            ->assertOk()
            ->assertSee('new-matter')
            ->assertSee('matterIntake', false);

        $this->actingAs($this->userWithRole(RoleEnum::Manager))
            ->get(route('matters'))
            ->assertOk()
            ->assertDontSee('matterIntake', false);
    }

    public function test_the_responsible_attorney_field_is_only_offered_to_users_who_may_assign(): void
    {
        $this->actingAs($this->userWithRole(RoleEnum::Secretary))
            ->get(route('matters'))
            ->assertViewHas('attorneys', fn ($users) => $users->isEmpty())
            ->assertDontSee('Responsible attorney');

        $this->actingAs($this->userWithRole(RoleEnum::Attorney))
            ->get(route('matters'))
            ->assertViewHas('attorneys', fn ($users) => $users->isNotEmpty())
            ->assertSee('Responsible attorney');
    }

    public function test_the_client_picker_finds_clients_by_name_with_masked_identifiers(): void
    {
        $client = Client::factory()->create(['first_name' => 'Thabo', 'last_name' => 'Mokoena', 'id_number' => '9001015800088']);
        Matter::factory()->for($client)->count(2)->create();
        Client::factory()->create(['first_name' => 'Zola', 'last_name' => 'Zulu']);

        $this->actingAs($this->userWithRole(RoleEnum::Secretary))
            ->getJson(route('client.lookup', ['q' => 'moko']))
            ->assertOk()
            ->assertExactJson([[
                'id' => $client->getKey(),
                'name' => 'Thabo Mokoena',
                'detail' => '·········0088',
                'matters' => 2,
            ]]);
    }

    public function test_the_client_picker_is_gated_behind_view_clients(): void
    {
        $this->actingAs($this->userWithRole(RoleEnum::Messenger))
            ->getJson(route('client.lookup', ['q' => 'a']))
            ->assertForbidden();
    }

    public function test_the_provisional_reference_matches_what_opening_would_issue(): void
    {
        $client = Client::factory()->create(['first_name' => 'Thabo', 'last_name' => 'Mokoena']);
        $type = MatterType::factory()->nonLitigation()->create(['code' => 'EST']);

        $secretary = $this->userWithRole(RoleEnum::Secretary);

        $preview = $this->actingAs($secretary)
            ->getJson(route('matter.reference-preview', ['client_id' => $client->getKey(), 'matter_type_id' => $type->getKey(), 'instructed_at' => '2026-10-01']))
            ->assertOk()
            ->json('reference');

        $this->assertSame('TM/EST/0001/2026', $preview);

        $this->actingAs($secretary)
            ->post(route('matter.store'), ['client_id' => $client->getKey(), 'matter_type_id' => $type->getKey(), 'instructed_at' => '2026-10-01']);

        $this->assertSame($preview, Matter::firstOrFail()->reference);
    }

    public function test_the_reference_preview_needs_the_create_permission_and_valid_input(): void
    {
        $this->actingAs($this->userWithRole(RoleEnum::Manager))
            ->getJson(route('matter.reference-preview'))
            ->assertForbidden();

        $this->actingAs($this->userWithRole(RoleEnum::Secretary))
            ->getJson(route('matter.reference-preview'))
            ->assertUnprocessable();
    }

    public function test_a_matter_is_opened_with_a_responsible_attorney_and_an_opening_narration(): void
    {
        ActivityType::factory()->create(['code' => 'ADMIN', 'name' => 'File administration']);
        $attorney = $this->userWithRole(RoleEnum::Attorney);
        $lead = $this->userWithRole(RoleEnum::Attorney);

        $this->actingAs($attorney)
            ->post(route('matter.store'), $this->payload([
                'responsible_attorney_id' => $lead->getKey(),
                'narration' => 'Instruction received; mandate signed.',
            ]))
            ->assertSessionHasNoErrors();

        $matter = Matter::firstOrFail()->load('assignments');

        $this->assertTrue($matter->responsibleAttorney()->is($lead));

        $narration = $matter->narrations()->with('activityType')->firstOrFail();

        $this->assertSame('Instruction received; mandate signed.', $narration->body);
        $this->assertSame('ADMIN', $narration->activityType->code);
        $this->assertTrue($narration->author->is($attorney));
        $this->assertSame(0, $narration->billableUnits());
    }

    public function test_without_the_extras_the_matter_opens_alone(): void
    {
        $this->actingAs($this->userWithRole(RoleEnum::Secretary))
            ->post(route('matter.store'), $this->payload())
            ->assertSessionHasNoErrors();

        $matter = Matter::firstOrFail();

        $this->assertCount(0, $matter->assignments);
        $this->assertCount(0, $matter->narrations);
    }

    public function test_choosing_an_attorney_needs_the_assign_permission(): void
    {
        $lead = $this->userWithRole(RoleEnum::Attorney);

        $this->actingAs($this->userWithRole(RoleEnum::Secretary))
            ->post(route('matter.store'), $this->payload(['responsible_attorney_id' => $lead->getKey()]))
            ->assertSessionHasErrorsIn('matter', 'responsible_attorney_id');

        $this->assertDatabaseCount('matters', 0);
    }

    public function test_a_failure_part_way_leaves_no_half_opened_matter(): void
    {
        // No ADMIN activity type exists, so filing the narration fails.
        $this->withoutExceptionHandling();

        try {
            $this->actingAs($this->userWithRole(RoleEnum::Secretary))
                ->post(route('matter.store'), $this->payload(['narration' => 'Opening words.']));
        } catch (\Throwable) {
            //
        }

        $this->assertDatabaseCount('matters', 0);
    }

    public function test_a_failed_submission_keeps_the_chosen_client_for_the_form(): void
    {
        $client = Client::factory()->create(['first_name' => 'Thabo', 'last_name' => 'Mokoena']);
        $secretary = $this->userWithRole(RoleEnum::Secretary);

        $this->actingAs($secretary)
            ->from(route('matters'))
            ->post(route('matter.store'), ['client_id' => $client->getKey()])
            ->assertSessionHasErrorsIn('matter', 'matter_type_id');

        $this->actingAs($secretary)
            ->get(route('matters'))
            ->assertViewHas('selectedClient', fn ($selected) => $selected?->is($client));
    }
}
