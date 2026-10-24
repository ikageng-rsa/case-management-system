<?php

declare(strict_types=1);

namespace Tests\Feature\Reference;

use App\Enums\Auth\Role as RoleEnum;
use App\Enums\Matter\Assignment;
use App\Models\Client\Client;
use App\Models\Matter\Matter;
use App\Models\Matter\MatterAssignment;
use App\Models\Narration;
use App\Models\Reference\ActivityType;
use App\Models\Reference\Court;
use App\Models\Reference\MatterType;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ReferenceDataTest extends TestCase
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

    protected function userWith(string ...$permissions): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user;
    }

    // ---- who sees what ----

    public function test_the_page_needs_at_least_one_of_the_three_permissions(): void
    {
        $this->actingAs($this->userWithRole(RoleEnum::Secretary))->get(route('reference-data'))->assertForbidden();
        $this->actingAs($this->userWithRole(RoleEnum::Manager))->get(route('reference-data'))->assertOk();
    }

    public function test_a_person_only_gets_the_tables_they_may_change(): void
    {
        $user = $this->userWith('manage_courts');

        $this->actingAs($user)->get(route('reference-data'))
            ->assertOk()
            ->assertViewHas('tabs', ['courts' => 'Courts'])
            ->assertViewHas('tab', 'courts');

        // Asking for another table lands on the one they are allowed.
        $this->actingAs($user)->get(route('reference-data', ['tab' => 'matter-types']))->assertViewHas('tab', 'courts');
    }

    public function test_each_table_follows_its_own_permission_on_the_way_in(): void
    {
        $courts = $this->userWith('manage_courts');
        $court = Court::factory()->create();
        $type = MatterType::factory()->create();

        $this->actingAs($courts)->post(route('court.store'), ['tier' => 'high', 'name' => 'High Court', 'seat' => 'Pretoria'])->assertSessionHasNoErrors();
        $this->actingAs($courts)->patch(route('matter-type.update', $type), ['name' => 'X'])->assertForbidden();
        $this->actingAs($courts)->delete(route('court.destroy', $court))->assertRedirect();
        $this->actingAs($courts)->post(route('activity-type.store'), ['code' => 'ABC', 'name' => 'X', 'measure' => 'item', 'increment' => 1])->assertForbidden();
    }

    public function test_the_sidebar_offers_the_page_only_to_those_who_may_use_it(): void
    {
        $this->actingAs($this->userWithRole(RoleEnum::Secretary))->get(route('dashboard'))
            ->assertDontSee('href="'.route('reference-data').'"', false);

        $this->actingAs($this->userWith('manage_courts'))->get(route('dashboard'))
            ->assertSee('href="'.route('reference-data').'"', false);
    }

    // ---- courts ----

    public function test_a_court_is_added_and_changed(): void
    {
        $director = $this->userWithRole(RoleEnum::Director);

        $this->actingAs($director)
            ->post(route('court.store'), ['tier' => 'high', 'name' => 'High Court', 'seat' => 'Johannesburg'])
            ->assertRedirect(route('reference-data', ['tab' => 'courts']));

        $court = Court::firstOrFail();
        $this->assertSame('High Court, Johannesburg', $court->label);

        $this->actingAs($director)
            ->patch(route('court.update', $court), ['tier' => 'high', 'name' => 'High Court', 'seat' => 'Pretoria'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Pretoria', $court->fresh()->seat);
    }

    public function test_a_court_needs_a_known_tier_a_name_and_a_seat(): void
    {
        $this->actingAs($this->userWithRole(RoleEnum::Director))
            ->post(route('court.store'), ['tier' => 'supreme', 'name' => '', 'seat' => ''])
            ->assertSessionHasErrorsIn('court_new', ['tier', 'name', 'seat']);
    }

    public function test_a_court_nothing_uses_is_deleted_outright(): void
    {
        $court = Court::factory()->create();

        $this->actingAs($this->userWithRole(RoleEnum::Director))->delete(route('court.destroy', $court));

        $this->assertDatabaseMissing('courts', ['id' => $court->getKey()]);
    }

    public function test_a_court_a_matter_uses_is_retired_and_the_file_still_reads_correctly(): void
    {
        $court = Court::factory()->create(['name' => 'High Court', 'seat' => 'Pretoria']);
        $matter = Matter::factory()->create(['court_id' => $court->getKey()]);
        $director = $this->userWithRole(RoleEnum::Director);

        $this->actingAs($director)->delete(route('court.destroy', $court))->assertSessionHasNoErrors();

        $this->assertSoftDeleted($court);
        $this->assertSame('High Court, Pretoria', $matter->fresh()->court->label, 'the matter still names its court');

        $this->actingAs($director)->get(route('matter.show', [$matter, 'tab' => 'parties']))->assertOk()->assertSee('High Court, Pretoria');
    }

    public function test_a_retired_court_is_no_longer_offered_or_accepted_and_can_be_restored(): void
    {
        $court = Court::factory()->create();
        Matter::factory()->create(['court_id' => $court->getKey()]);
        $director = $this->userWithRole(RoleEnum::Director);
        $type = MatterType::factory()->create(['is_litigation' => true]);
        $client = Client::factory()->create();

        $this->actingAs($director)->delete(route('court.destroy', $court));

        $this->actingAs($director)->get(route('matters'))->assertViewHas('courts', fn ($courts) => $courts->doesntContain('id', $court->getKey()));

        $this->actingAs($director)
            ->post(route('matter.store'), ['client_id' => $client->getKey(), 'matter_type_id' => $type->getKey(), 'court_id' => $court->getKey()])
            ->assertSessionHasErrorsIn('matter', 'court_id');

        $this->actingAs($director)->post(route('court.restore', $court))->assertSessionHasNoErrors();

        $this->assertNull($court->fresh()->deleted_at);
    }

    // ---- matter types ----

    public function test_a_matter_type_is_added_with_its_code_in_capitals(): void
    {
        $this->actingAs($this->userWithRole(RoleEnum::Director))
            ->post(route('matter-type.store'), ['code' => ' lab ', 'name' => 'Labour', 'default_prescription_months' => 36, 'is_litigation' => '1'])
            ->assertSessionHasNoErrors();

        $type = MatterType::firstOrFail();

        $this->assertSame('LAB', $type->code);
        $this->assertSame(36, $type->default_prescription_months);
        $this->assertTrue($type->is_litigation);
    }

    public function test_a_code_is_two_to_eight_capital_letters_and_never_reused_even_by_a_retired_type(): void
    {
        $director = $this->userWithRole(RoleEnum::Director);
        MatterType::factory()->create(['code' => 'CIV'])->delete();

        foreach (['A', 'TOOLONGCODE', 'C1V', 'CIV'] as $bad) {
            $this->actingAs($director)
                ->post(route('matter-type.store'), ['code' => $bad, 'name' => 'Whatever'])
                ->assertSessionHasErrorsIn('matter_type_new', 'code');
        }
    }

    public function test_the_code_is_fixed_once_a_matter_carries_it_but_the_rest_may_change(): void
    {
        $type = MatterType::factory()->create(['code' => 'CRM', 'name' => 'Criminal', 'is_litigation' => false]);
        Matter::factory()->create(['matter_type_id' => $type->getKey()]);
        $director = $this->userWithRole(RoleEnum::Director);

        $this->actingAs($director)
            ->patch(route('matter-type.update', $type), ['code' => 'NEW', 'name' => 'Criminal law'])
            ->assertSessionHasErrorsIn('matter_type_'.$type->getKey(), 'code');

        $this->assertSame('CRM', $type->fresh()->code);

        // The form does not send a locked code, so a change to the rest goes through.
        $this->actingAs($director)
            ->patch(route('matter-type.update', $type), ['name' => 'Criminal law', 'default_prescription_months' => 24, 'is_litigation' => '1'])
            ->assertSessionHasNoErrors();

        $type->refresh();

        $this->assertSame('CRM', $type->code);
        $this->assertSame('Criminal law', $type->name);
        $this->assertTrue($type->is_litigation);
    }

    public function test_the_code_of_a_type_nothing_has_used_may_change(): void
    {
        $type = MatterType::factory()->create(['code' => 'OLD']);

        $this->actingAs($this->userWithRole(RoleEnum::Director))
            ->patch(route('matter-type.update', $type), ['code' => 'NEW', 'name' => 'Renamed'])
            ->assertSessionHasNoErrors();

        $this->assertSame('NEW', $type->fresh()->code);
    }

    public function test_a_type_with_matters_is_retired_and_its_matters_still_read(): void
    {
        $type = MatterType::factory()->create(['code' => 'CRM', 'name' => 'Criminal']);
        $matter = Matter::factory()->create(['matter_type_id' => $type->getKey()]);
        $director = $this->userWithRole(RoleEnum::Director);

        $this->actingAs($director)->delete(route('matter-type.destroy', $type));

        $this->assertSoftDeleted($type);
        $this->actingAs($director)->get(route('matter.show', $matter))->assertOk()->assertSee('Criminal');
        $this->actingAs($director)->get(route('matters'))->assertSee($matter->reference);

        $this->actingAs($director)->get(route('matters'))
            ->assertViewHas('matterTypes', fn ($types) => $types->doesntContain('id', $type->getKey()));

        $this->actingAs($director)->post(route('matter-type.restore', $type));
        $this->assertNull($type->fresh()->deleted_at);
    }

    public function test_an_unused_type_is_deleted_outright(): void
    {
        $type = MatterType::factory()->create();

        $this->actingAs($this->userWithRole(RoleEnum::Director))->delete(route('matter-type.destroy', $type));

        $this->assertDatabaseMissing('matter_types', ['id' => $type->getKey()]);
    }

    // ---- kinds of work ----

    public function test_a_kind_of_work_is_added(): void
    {
        $this->actingAs($this->userWithRole(RoleEnum::Director))
            ->post(route('activity-type.store'), [
                'code' => 'conf', 'name' => 'Conference', 'measure' => 'minutes', 'increment' => 15, 'default_billable' => '1', 'requires_court' => '0',
            ])
            ->assertSessionHasNoErrors();

        $type = ActivityType::firstOrFail();

        $this->assertSame('CONF', $type->code);
        $this->assertSame(15, $type->increment);
        $this->assertTrue($type->default_billable);
        $this->assertFalse($type->requires_court);
    }

    public function test_how_work_is_counted_is_fixed_once_a_narration_exists(): void
    {
        $type = ActivityType::factory()->create(['code' => 'CALL', 'name' => 'Call', 'increment' => 6]);
        Narration::factory()->create(['activity_type_id' => $type->getKey(), 'quantity' => 10]);
        $director = $this->userWithRole(RoleEnum::Director);

        $this->actingAs($director)
            ->patch(route('activity-type.update', $type), ['name' => 'Call', 'measure' => 'minutes', 'increment' => 15])
            ->assertSessionHasErrorsIn('activity_type_'.$type->getKey(), 'measure');

        $this->assertSame(6, $type->fresh()->increment);

        // The words and the flags may still change.
        $this->actingAs($director)
            ->patch(route('activity-type.update', $type), ['name' => 'Telephone call', 'default_billable' => '0'])
            ->assertSessionHasNoErrors();

        $type->refresh();

        $this->assertSame('Telephone call', $type->name);
        $this->assertFalse($type->default_billable);
        $this->assertSame(6, $type->increment);
    }

    public function test_how_work_is_counted_may_change_before_anything_uses_it(): void
    {
        $type = ActivityType::factory()->create(['increment' => 6]);

        $this->actingAs($this->userWithRole(RoleEnum::Director))
            ->patch(route('activity-type.update', $type), ['name' => $type->name, 'measure' => 'page', 'increment' => 1])
            ->assertSessionHasNoErrors();

        $type->refresh();

        $this->assertSame('page', $type->measure->value);
        $this->assertSame(1, $type->increment);
    }

    public function test_the_system_kind_cannot_be_retired_or_have_its_code_changed(): void
    {
        $admin = ActivityType::factory()->create(['code' => 'ADMIN', 'name' => 'File administration']);
        $director = $this->userWithRole(RoleEnum::Director);

        $this->actingAs($director)->delete(route('activity-type.destroy', $admin))
            ->assertSessionHasErrorsIn('activity_types', 'activity_type');

        $this->actingAs($director)->patch(route('activity-type.update', $admin), ['code' => 'FILING', 'name' => 'File administration'])
            ->assertSessionHasErrorsIn('activity_type_'.$admin->getKey(), 'measure');

        $this->assertNotNull(ActivityType::query()->where('code', 'ADMIN')->first());
    }

    public function test_a_retired_kind_keeps_billing_its_narrations_and_is_no_longer_offered(): void
    {
        $type = ActivityType::factory()->create(['code' => 'CALL', 'increment' => 15]);
        $narration = Narration::factory()->create(['activity_type_id' => $type->getKey(), 'quantity' => 20]);
        $director = $this->userWithRole(RoleEnum::Director);

        $this->actingAs($director)->delete(route('activity-type.destroy', $type));

        $this->assertSoftDeleted($type);
        $this->assertSame(2, $narration->fresh()->billableUnits(), 'twenty minutes in fifteens is still two units');

        $matter = $narration->matter;
        $attorney = $this->userWithRole(RoleEnum::Attorney);
        MatterAssignment::factory()->for($matter)->for($attorney)->capacity(Assignment::Responsible)->create();

        $this->actingAs($attorney)
            ->post(route('matter.narrations.store', $matter), ['activity_type_id' => $type->getKey(), 'quantity' => 5, 'body' => 'More.'])
            ->assertSessionHasErrorsIn('narration', 'activity_type_id');

        $this->actingAs($attorney)->get(route('matter.show', $matter))
            ->assertViewHas('activityTypes', fn ($types) => $types->doesntContain('id', $type->getKey()));
    }

    // ---- the record of it ----

    public function test_changes_are_audited_once_by_the_model(): void
    {
        $court = Court::factory()->create(['name' => 'High Court', 'seat' => 'Pretoria']);
        Activity::query()->delete();

        $this->actingAs($this->userWithRole(RoleEnum::Director))
            ->patch(route('court.update', $court), ['tier' => $court->tier->value, 'name' => 'High Court', 'seat' => 'Johannesburg']);

        $this->assertSame(1, Activity::forSubject($court)->where('event', 'updated')->count());
    }

    public function test_retiring_is_recorded(): void
    {
        $court = Court::factory()->create();
        Matter::factory()->create(['court_id' => $court->getKey()]);
        Activity::query()->delete();

        $this->actingAs($this->userWithRole(RoleEnum::Director))->delete(route('court.destroy', $court));

        $this->assertSame(1, Activity::forSubject($court)->where('event', 'deleted')->count());
    }

    // ---- the page itself ----

    public function test_every_table_renders_with_its_rows_its_counts_and_its_retired_entries(): void
    {
        $director = $this->userWithRole(RoleEnum::Director);

        $court = Court::factory()->create(['name' => 'High Court', 'seat' => 'Pretoria']);
        Matter::factory()->count(2)->create(['court_id' => $court->getKey()]);
        Court::factory()->create(['name' => 'Old Court', 'seat' => 'Nowhere'])->delete();

        $type = MatterType::factory()->create(['code' => 'CRM', 'name' => 'Criminal']);
        Matter::factory()->create(['matter_type_id' => $type->getKey()]);
        MatterType::factory()->create(['code' => 'OLD', 'name' => 'Old matter type'])->delete();
        MatterType::factory()->create(['code' => 'NEW', 'name' => 'Unused matter type']);

        $work = ActivityType::factory()->create(['code' => 'CALL', 'name' => 'Telephone attendance']);
        Narration::factory()->count(3)->create(['activity_type_id' => $work->getKey()]);
        ActivityType::factory()->create(['code' => 'ADMIN', 'name' => 'File administration']);
        ActivityType::factory()->create(['code' => 'GONE', 'name' => 'Gone kind'])->delete();

        $this->actingAs($director)->get(route('reference-data', ['tab' => 'courts']))
            ->assertOk()->assertSee('High Court, Pretoria')->assertSee('Old Court, Nowhere')->assertSee('Retired')->assertSee('Restore');

        $this->actingAs($director)->get(route('reference-data', ['tab' => 'matter-types']))
            ->assertOk()->assertSee('Criminal')->assertSee('Old matter type')->assertSee('Retired')
            ->assertSee('Retire')->assertSee('Delete');

        $this->actingAs($director)->get(route('reference-data', ['tab' => 'activity-types']))
            ->assertOk()->assertSee('Telephone attendance')->assertSee('System')->assertSee('Gone kind')->assertSee('Retired');
    }

    public function test_a_locked_field_is_shown_disabled_with_the_reason(): void
    {
        $type = MatterType::factory()->create(['code' => 'CRM']);
        Matter::factory()->create(['matter_type_id' => $type->getKey()]);

        $this->actingAs($this->userWithRole(RoleEnum::Director))
            ->get(route('reference-data', ['tab' => 'matter-types']))
            ->assertSee('Fixed: matters of this type carry it in their reference.');
    }
}
