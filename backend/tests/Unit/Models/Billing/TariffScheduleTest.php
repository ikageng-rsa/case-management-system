<?php

declare(strict_types=1);

namespace Tests\Unit\Models\Billing;

use App\Models\Billing\TariffSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TariffScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_schedule_in_force_is_the_latest_to_have_started_by_the_date(): void
    {
        $older = TariffSchedule::factory()->startingOn('2025-04-01')->create();
        $newer = TariffSchedule::factory()->startingOn('2026-04-01')->create();
        TariffSchedule::factory()->startingOn('2027-04-01')->create();

        $this->assertTrue(TariffSchedule::inForceOn(Carbon::parse('2025-12-31'))->is($older));
        $this->assertTrue(TariffSchedule::inForceOn(Carbon::parse('2026-04-01'))->is($newer));
        $this->assertNull(TariffSchedule::inForceOn(Carbon::parse('2024-01-01')));
    }

    public function test_a_schedule_dated_after_today_has_not_started(): void
    {
        $this->assertFalse(TariffSchedule::factory()->upcoming()->create()->hasStarted());
        $this->assertTrue(TariffSchedule::factory()->startingOn(today()->toDateString())->create()->hasStarted());
    }
}
