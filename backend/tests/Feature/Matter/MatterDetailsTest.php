<?php

declare(strict_types=1);

namespace Tests\Feature\Matter;

use App\Enums\Auth\Role as RoleEnum;
use App\Enums\Matter\Assignment;
use App\Models\Court;
use App\Models\Matter\Matter;
use App\Models\Matter\MatterAssignment;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class MatterDetailsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RoleSeeder::class);
    }

    protected function carrying(Matter $matter, RoleEnum $role = RoleEnum::Attorney): User
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);
        MatterAssignment::factory()->for($matter)->for($user)->capacity(Assignment::Responsible)->create();

        return $user;
    }

    public function test_the_title_the_date_and_the_court_are_changed_in_place_and_logged_once(): void
    {
        $matter = Matter::factory()->create(['title' => 'Old title']);
        $user = $this->carrying($matter);
        $court = Court::factory()->create();
        Activity::query()->delete();

        $this->actingAs($user)->patch(route('matter.update', $matter), ['title' => 'Mokoena v RAF'])->assertRedirect(route('matter.show', $matter));
        $this->actingAs($user)->patch(route('matter.update', $matter), ['instructed_at' => '2026-03-02']);
        $this->actingAs($user)->patch(route('matter.update', $matter), ['court_id' => $court->getKey()]);

        $fresh = $matter->fresh();
        $this->assertSame('Mokoena v RAF', $fresh->title);
        $this->assertSame('2026-03-02', $fresh->instructed_at->format('Y-m-d'));
        $this->assertSame($court->getKey(), $fresh->court_id);

        $this->assertSame(3, Activity::forSubject($matter)->where('event', 'updated')->count(), 'each change once');
    }

    public function test_only_what_was_sent_is_touched(): void
    {
        $matter = Matter::factory()->create(['title' => 'Keep me']);
        $prescribes = $matter->prescribes_at->toDateString();

        $this->actingAs($this->carrying($matter))->patch(route('matter.update', $matter), ['instructed_at' => '2026-03-02']);

        $this->assertSame('Keep me', $matter->fresh()->title);
        $this->assertSame($prescribes, $matter->fresh()->prescribes_at->toDateString());
    }

    public function test_the_prescription_date_needs_a_reason(): void
    {
        $matter = Matter::factory()->create();
        $before = $matter->prescribes_at->toDateString();

        $this->actingAs($this->carrying($matter))
            ->patch(route('matter.update', $matter), ['prescribes_at' => '2030-01-01'])
            ->assertSessionHasErrors('reason');

        $this->assertSame($before, $matter->fresh()->prescribes_at->toDateString());
    }

    public function test_changing_it_records_old_new_and_the_reason_once(): void
    {
        $matter = Matter::factory()->create(['prescribes_at' => '2029-05-01']);
        $user = $this->carrying($matter);
        Activity::query()->delete();

        $this->actingAs($user)
            ->patch(route('matter.update', $matter), ['prescribes_at' => '2029-08-15', 'reason' => 'Summons served later than recorded.'])
            ->assertSessionHasNoErrors();

        $this->assertSame('2029-08-15', $matter->fresh()->prescribes_at->format('Y-m-d'));

        $events = Activity::forSubject($matter)->get();
        $this->assertCount(1, $events, 'not also an updated entry');
        $event = $events->sole();
        $this->assertSame('prescription_changed', $event->event);
        $this->assertSame('01 May 2029', $event->getProperty('from'));
        $this->assertSame('15 Aug 2029', $event->getProperty('to'));
        $this->assertSame('Summons served later than recorded.', $event->getProperty('reason'));
        $this->assertTrue($event->causer->is($user));
    }

    public function test_saving_the_same_date_changes_and_records_nothing(): void
    {
        $matter = Matter::factory()->create(['prescribes_at' => '2029-05-01']);
        $user = $this->carrying($matter);
        Activity::query()->delete();

        $this->actingAs($user)->patch(route('matter.update', $matter), ['prescribes_at' => '2029-05-01', 'reason' => 'No change']);

        $this->assertSame(0, Activity::forSubject($matter)->count());
    }

    public function test_a_court_can_be_cleared_but_not_set_on_a_matter_that_is_not_litigious(): void
    {
        $matter = Matter::factory()->create();
        $user = $this->carrying($matter);

        $this->actingAs($user)->patch(route('matter.update', $matter), ['court_id' => null]);
        $this->assertNull($matter->fresh()->court_id);

        $plain = Matter::factory()->nonLitigation()->create(['court_id' => null]);
        $this->actingAs($this->carrying($plain))->patch(route('matter.update', $plain), ['court_id' => Court::factory()->create()->getKey()]);
        $this->assertNull($plain->fresh()->court_id);
    }

    public function test_it_is_its_own_permission(): void
    {
        $matter = Matter::factory()->create(['title' => 'Same']);
        $manager = User::factory()->create();
        $manager->assignRole(RoleEnum::Manager->value);
        $partiesOnly = User::factory()->create();
        $partiesOnly->givePermissionTo(['edit_matters', 'view_all_matters']);

        foreach ([$manager, $partiesOnly] as $who) {
            $this->actingAs($who)->patch(route('matter.update', $matter), ['title' => 'Changed'])->assertForbidden();
        }

        $this->assertSame('Same', $matter->fresh()->title);
    }

    public function test_it_is_for_the_team_and_those_who_may_see_every_file(): void
    {
        $matter = Matter::factory()->create(['title' => 'Same']);
        $stranger = User::factory()->create();
        $stranger->assignRole(RoleEnum::Attorney->value);

        $this->actingAs($stranger)->patch(route('matter.update', $matter), ['title' => 'Changed'])->assertForbidden();

        $director = User::factory()->create();
        $director->assignRole(RoleEnum::Director->value);
        $this->actingAs($director)->patch(route('matter.update', $matter), ['title' => 'By director'])->assertSessionHasNoErrors();
        $this->assertSame('By director', $matter->fresh()->title);
    }

    public function test_a_closed_matter_is_not_changed_until_it_is_reopened(): void
    {
        $matter = Matter::factory()->closed()->create(['title' => 'Same']);
        $director = User::factory()->create();
        $director->assignRole(RoleEnum::Director->value);

        $this->actingAs($director)->patch(route('matter.update', $matter), ['title' => 'Changed'])->assertForbidden();

        $this->assertSame('Same', $matter->fresh()->title);
    }

    public function test_the_type_the_client_and_the_reference_are_not_changed_this_way(): void
    {
        $matter = Matter::factory()->create();
        $user = $this->carrying($matter);
        $reference = $matter->reference;
        $type = $matter->matter_type_id;
        $client = $matter->client_id;

        $this->actingAs($user)->patch(route('matter.update', $matter), [
            'title' => 'Fine', 'reference' => 'X/1/2020', 'matter_type_id' => 999, 'client_id' => 'nope',
        ])->assertSessionHasNoErrors();

        $fresh = $matter->fresh();
        $this->assertSame($reference, $fresh->reference);
        $this->assertSame($type, $fresh->matter_type_id);
        $this->assertSame($client, $fresh->client_id);
    }

    public function test_the_page_offers_the_pencils_to_those_who_may_and_not_to_others(): void
    {
        $matter = Matter::factory()->create();
        $attorney = $this->carrying($matter);

        $html = $this->actingAs($attorney)->get(route('matter.show', $matter))->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, 'name="title"'));
        $this->assertStringContainsString('name="instructed_at"', $html);
        $this->assertStringContainsString('aria-label="Court"', $html);
        $this->assertStringContainsString('name="prescribes_at"', $html);
        $this->assertStringContainsString('data-confirm-prompt-field="reason"', $html);

        $manager = User::factory()->create();
        $manager->assignRole(RoleEnum::Manager->value);
        $html = $this->actingAs($manager)->get(route('matter.show', $matter))->assertOk()->getContent();
        $this->assertStringNotContainsString('name="prescribes_at"', $html);
        $this->assertStringNotContainsString('name="title"', $html);
    }

    public function test_the_court_is_not_shown_for_a_matter_that_is_not_litigious(): void
    {
        $matter = Matter::factory()->nonLitigation()->create(['court_id' => null]);

        $html = $this->actingAs($this->carrying($matter))->get(route('matter.show', $matter))->assertOk()->getContent();

        $this->assertStringNotContainsString('aria-label="Court"', $html);
        $this->assertStringContainsString('name="prescribes_at"', $html);
    }
}
