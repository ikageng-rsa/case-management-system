<?php

declare(strict_types=1);

namespace Tests\Unit\Models\Billing;

use App\Enums\Billing\TariffScale;
use App\Models\Billing\TariffAdjustment;
use App\Models\Billing\TariffItem;
use App\Models\Billing\TariffSchedule;
use App\Models\User\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TariffScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_schedule_in_force_is_the_latest_published_one_to_have_started_by_the_date(): void
    {
        $older = TariffSchedule::factory()->startingOn('2025-04-01')->create();
        $newer = TariffSchedule::factory()->startingOn('2026-04-01')->create();
        TariffSchedule::factory()->startingOn('2026-06-01')->draft()->create();
        TariffSchedule::factory()->startingOn('2027-04-01')->create();

        $this->assertTrue(TariffSchedule::inForceOn(Carbon::parse('2025-12-31'))->is($older));
        $this->assertTrue(TariffSchedule::inForceOn(Carbon::parse('2026-07-01'))->is($newer));
        $this->assertNull(TariffSchedule::inForceOn(Carbon::parse('2024-01-01')));
    }

    public function test_it_reads_as_draft_upcoming_in_force_or_replaced(): void
    {
        $replaced = TariffSchedule::factory()->startingOn(today()->subYear()->toDateString())->create();
        $inForce = TariffSchedule::factory()->startingOn(today()->toDateString())->create();
        $current = TariffSchedule::inForceOn(today());

        $this->assertSame('draft', TariffSchedule::factory()->draft()->create()->status($current));
        $this->assertSame('upcoming', TariffSchedule::factory()->upcoming()->create()->status($current));
        $this->assertSame('in_force', $inForce->status($current));
        $this->assertSame('replaced', $replaced->status($current));
    }

    public function test_a_draft_has_not_started_whatever_its_date(): void
    {
        $this->assertFalse(TariffSchedule::factory()->startingOn('2020-01-01')->draft()->create()->hasStarted());
        $this->assertFalse(TariffSchedule::factory()->upcoming()->create()->hasStarted());
        $this->assertTrue(TariffSchedule::factory()->startingOn(today()->toDateString())->create()->hasStarted());
    }

    public function test_a_role_charges_the_gazette_rate_with_its_markup(): void
    {
        $schedule = TariffSchedule::factory()->create();
        $item = TariffItem::factory()->for($schedule, 'schedule')->create(['scale_a_cents' => 144000, 'scale_b_cents' => 180000]);
        $candidate = Role::findOrCreate('candidate_attorney');
        $director = Role::findOrCreate('director');
        TariffAdjustment::factory()->for($schedule, 'schedule')->percent(-50)->create(['role_id' => $candidate->getKey()]);

        $this->assertSame(72000, $schedule->rateFor($item, TariffScale::A, $candidate));
        $this->assertSame(90000, $schedule->rateFor($item, TariffScale::B, $candidate));
        $this->assertSame(144000, $schedule->rateFor($item, TariffScale::A, $director));
        $this->assertSame(144000, $schedule->rateFor($item, TariffScale::A, null));
        $this->assertNull($schedule->rateFor($item, TariffScale::C, $candidate));
    }
}
