<?php

declare(strict_types=1);

namespace Tests\Feature\Matter;

use App\Enums\Auth\Role as RoleEnum;
use App\Enums\Matter\Assignment;
use App\Models\ActivityType;
use App\Models\Matter\Matter;
use App\Models\Matter\MatterAssignment;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class MatterStatusTest extends TestCase
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

    protected function carrying(Matter $matter, RoleEnum $role): User
    {
        $user = $this->userWithRole($role);
        MatterAssignment::factory()->for($matter)->for($user)->capacity(Assignment::Responsible)->create();

        return $user;
    }

    // ---- closing ----

    public function test_a_matter_is_closed_by_someone_who_may_close_matters(): void
    {
        $matter = Matter::factory()->create();
        $attorney = $this->carrying($matter, RoleEnum::Attorney);

        $this->actingAs($attorney)
            ->post(route('matter.close', $matter))
            ->assertRedirect(route('matter.show', $matter))
            ->assertSessionHas('status', 'Matter closed.');

        $this->assertTrue($matter->fresh()->isClosed());
    }

    public function test_closing_is_logged_once_as_the_act_with_who_and_when(): void
    {
        $matter = Matter::factory()->create();
        $attorney = $this->carrying($matter, RoleEnum::Attorney);
        Activity::query()->delete();

        $this->actingAs($attorney)->post(route('matter.close', $matter));

        $closed = Activity::forSubject($matter)->where('event', 'closed')->get();

        $this->assertCount(1, $closed);
        $this->assertTrue($closed->first()->causer->is($attorney));
        $this->assertNotNull($closed->first()->properties['closed_at']);
        $this->assertSame(0, Activity::forSubject($matter)->where('event', 'updated')->count(), 'the act is not also logged as an edit of a field');
    }

    public function test_closing_a_closed_matter_changes_and_logs_nothing(): void
    {
        $matter = Matter::factory()->closed()->create();
        $attorney = $this->carrying($matter, RoleEnum::Attorney);
        Activity::query()->delete();

        $this->actingAs($attorney)->post(route('matter.close', $matter));

        $this->assertSame(0, Activity::forSubject($matter)->where('event', 'closed')->count());
    }

    public function test_closing_needs_the_close_permission(): void
    {
        $matter = Matter::factory()->create();

        $this->actingAs($this->carrying($matter, RoleEnum::CandidateAttorney))
            ->post(route('matter.close', $matter))
            ->assertForbidden();

        $this->assertTrue($matter->fresh()->isOpen());
    }

    public function test_a_closing_date_cannot_be_slipped_in_through_an_edit(): void
    {
        $matter = Matter::factory()->create();

        $matter->fill(['closed_at' => now()]);

        $this->assertNull($matter->closed_at);

        $this->actingAs($this->userWithRole(RoleEnum::Director))
            ->patch(route('matter.update', $matter), ['title' => 'Renamed', 'closed_at' => now()->toDateTimeString()])
            ->assertSessionHasNoErrors();

        $this->assertTrue($matter->fresh()->isOpen());
    }

    // ---- reopening ----

    public function test_a_closed_matter_is_reopened_with_a_reason_and_it_is_logged(): void
    {
        $matter = Matter::factory()->closed()->create();
        $director = $this->userWithRole(RoleEnum::Director);
        $closedSince = $matter->closed_at->toIso8601String();
        Activity::query()->delete();

        $this->actingAs($director)
            ->post(route('matter.reopen', $matter), ['reason' => 'Client has instructed a further application.'])
            ->assertRedirect(route('matter.show', $matter))
            ->assertSessionHas('status', 'Matter reopened.');

        $this->assertTrue($matter->fresh()->isOpen());

        $reopened = Activity::forSubject($matter)->where('event', 'reopened')->get();

        $this->assertCount(1, $reopened);
        $this->assertTrue($reopened->first()->causer->is($director));
        $this->assertSame('Client has instructed a further application.', $reopened->first()->properties['reason']);
        $this->assertSame($closedSince, $reopened->first()->properties['was_closed_since']);
    }

    public function test_reopening_needs_a_reason(): void
    {
        $matter = Matter::factory()->closed()->create();

        $this->actingAs($this->userWithRole(RoleEnum::Director))
            ->post(route('matter.reopen', $matter), ['reason' => ''])
            ->assertSessionHasErrorsIn('status', 'reason');

        $this->assertTrue($matter->fresh()->isClosed());
    }

    public function test_being_able_to_close_is_not_being_able_to_reopen(): void
    {
        $matter = Matter::factory()->closed()->create();
        $attorney = $this->carrying($matter, RoleEnum::Attorney);

        $this->assertTrue($attorney->can('close', $matter));

        $this->actingAs($attorney)
            ->post(route('matter.reopen', $matter), ['reason' => 'Changed my mind.'])
            ->assertForbidden();

        $this->assertTrue($matter->fresh()->isClosed());
    }

    public function test_reopening_an_open_matter_changes_and_logs_nothing(): void
    {
        $matter = Matter::factory()->create();
        Activity::query()->delete();

        $this->actingAs($this->userWithRole(RoleEnum::Director))
            ->post(route('matter.reopen', $matter), ['reason' => 'Not needed.']);

        $this->assertSame(0, Activity::forSubject($matter)->where('event', 'reopened')->count());
    }

    public function test_a_reopened_matter_takes_work_again(): void
    {
        $matter = Matter::factory()->closed()->create();
        $attorney = $this->carrying($matter, RoleEnum::Attorney);
        $type = ActivityType::factory()->create();
        $payload = ['activity_type_id' => $type->getKey(), 'quantity' => 6, 'body' => 'Back at it.'];

        $this->actingAs($attorney)->post(route('matter.narrations.store', $matter), $payload)->assertForbidden();

        $this->actingAs($this->userWithRole(RoleEnum::Director))
            ->post(route('matter.reopen', $matter), ['reason' => 'Further instructions.']);

        $this->actingAs($attorney)->post(route('matter.narrations.store', $matter), $payload)->assertSessionHasNoErrors();
        $this->assertCount(1, $matter->fresh()->narrations);
    }

    // ---- what the status value offers ----

    public function test_someone_who_may_close_is_offered_closing_on_an_open_matter_and_nothing_else(): void
    {
        $matter = Matter::factory()->create();

        $html = $this->actingAs($this->carrying($matter, RoleEnum::Attorney))
            ->get(route('matter.show', $matter))
            ->assertOk()
            ->assertSee('Close this matter?')
            ->assertSee('This cannot be undone here')
            ->getContent();

        $this->assertStringContainsString('<option value="closed">Closed</option>', $html);
        $this->assertStringNotContainsString('<option value="open">Open</option>', $html);
        $this->assertStringNotContainsString('Reopen this matter?', $html);
    }

    public function test_someone_who_may_not_close_sees_the_status_as_plain_text(): void
    {
        $matter = Matter::factory()->create();

        $this->actingAs($this->carrying($matter, RoleEnum::CandidateAttorney))
            ->get(route('matter.show', $matter))
            ->assertOk()
            ->assertDontSee('Close this matter?')
            ->assertDontSee(route('matter.close', $matter), false);
    }

    public function test_someone_who_may_reopen_is_offered_it_on_a_closed_matter_and_asked_why(): void
    {
        $matter = Matter::factory()->closed()->create();

        $this->actingAs($this->userWithRole(RoleEnum::Director))
            ->get(route('matter.show', $matter))
            ->assertSee('Reopen this matter?')
            ->assertSee('data-confirm-prompt-label="Why is it being reopened?"', false)
            ->assertSee('<option value="open">Open</option>', false)
            ->assertSee('since '.$matter->closed_at->format('d M Y'));
    }

    public function test_a_closer_who_cannot_reopen_is_not_offered_it_on_a_closed_matter(): void
    {
        $matter = Matter::factory()->closed()->create();

        $this->actingAs($this->carrying($matter, RoleEnum::Attorney))
            ->get(route('matter.show', $matter))
            ->assertOk()
            ->assertDontSee('Reopen this matter?')
            ->assertDontSee(route('matter.reopen', $matter), false)
            ->assertSee('since '.$matter->closed_at->format('d M Y'));
    }

    public function test_closing_and_reopening_show_up_in_the_audit_log_page(): void
    {
        $matter = Matter::factory()->create();
        $director = $this->userWithRole(RoleEnum::Director);

        $this->actingAs($director)->post(route('matter.close', $matter));
        $this->actingAs($director)->post(route('matter.reopen', $matter), ['reason' => 'Further instructions received.']);

        $details = $this->actingAs($director)->get(route('audit-log'))->viewData('details');

        $reopened = $details->first(fn ($detail) => $detail['event'] === 'reopened');

        $this->assertNotNull($details->first(fn ($detail) => $detail['event'] === 'closed'));
        $this->assertSame('Further instructions received.', collect($reopened['properties'])->firstWhere('key', 'reason')['value']);
    }
}
