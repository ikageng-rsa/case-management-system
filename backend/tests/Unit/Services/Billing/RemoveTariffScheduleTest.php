<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Billing;

use App\Models\Billing\TariffItem;
use App\Models\Billing\TariffSchedule;
use App\Services\Billing\RemoveTariffSchedule;
use App\Services\Billing\TariffScheduleHasStarted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RemoveTariffScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_upcoming_schedule_is_removed_with_its_rates(): void
    {
        $schedule = TariffSchedule::factory()->upcoming()->create();
        TariffItem::factory()->for($schedule, 'schedule')->create();

        (new RemoveTariffSchedule)->remove($schedule);

        $this->assertModelMissing($schedule);
        $this->assertSame(0, TariffItem::count());
    }

    public function test_a_draft_is_removed_whatever_its_date(): void
    {
        $schedule = TariffSchedule::factory()->startingOn('2020-01-01')->draft()->create();

        (new RemoveTariffSchedule)->remove($schedule);

        $this->assertModelMissing($schedule);
    }

    public function test_a_schedule_in_force_stays_on_record(): void
    {
        $schedule = TariffSchedule::factory()->startingOn(today()->toDateString())->create();

        $this->expectException(TariffScheduleHasStarted::class);

        (new RemoveTariffSchedule)->remove($schedule);
    }
}
