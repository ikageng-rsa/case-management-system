<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Billing;

use App\Models\Billing\TariffSchedule;
use App\Services\Billing\PublishTariffSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublishTariffScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_published_draft_comes_into_force_on_its_date(): void
    {
        $schedule = TariffSchedule::factory()->startingOn(today()->toDateString())->draft()->create();
        $this->assertNull(TariffSchedule::inForceOn(today()));

        (new PublishTariffSchedule)->publish($schedule);

        $this->assertTrue(TariffSchedule::inForceOn(today())->is($schedule));
    }

    public function test_publishing_again_keeps_the_first_publication(): void
    {
        $schedule = TariffSchedule::factory()->create(['published_at' => now()->subWeek()]);
        $publishedAt = $schedule->published_at;

        (new PublishTariffSchedule)->publish($schedule);

        $this->assertTrue($schedule->fresh()->published_at->eq($publishedAt));
    }
}
