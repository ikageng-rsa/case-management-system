<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Billing;

use App\Enums\Billing\TariffAdjustmentKind;
use App\Models\Billing\TariffAdjustment;
use App\Models\Billing\TariffSchedule;
use App\Models\User\Role;
use App\Services\Billing\SaveTariffAdjustments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaveTariffAdjustmentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sets_changes_and_clears_each_roles_markup(): void
    {
        $schedule = TariffSchedule::factory()->draft()->create();
        $director = Role::findOrCreate('director');
        $candidate = Role::findOrCreate('candidate_attorney');
        $secretary = Role::findOrCreate('legal_secretary');
        TariffAdjustment::factory()->for($schedule, 'schedule')->percent(-50)->create(['role_id' => $candidate->getKey()]);
        TariffAdjustment::factory()->for($schedule, 'schedule')->percent(-60)->create(['role_id' => $secretary->getKey()]);

        (new SaveTariffAdjustments)->save($schedule, [
            $director->getKey() => ['kind' => TariffAdjustmentKind::Percent, 'value' => 4545],
            $candidate->getKey() => ['kind' => TariffAdjustmentKind::Amount, 'value' => -1500],
            $secretary->getKey() => null,
        ]);

        $adjustments = $schedule->adjustments()->get()->keyBy('role_id');

        $this->assertCount(2, $adjustments);
        $this->assertSame(4545, $adjustments[$director->getKey()]->value);
        $this->assertSame(TariffAdjustmentKind::Amount, $adjustments[$candidate->getKey()]->kind);
        $this->assertSame(-1500, $adjustments[$candidate->getKey()]->value);
    }

    public function test_it_leaves_other_tariffs_alone(): void
    {
        $role = Role::findOrCreate('director');
        $other = TariffAdjustment::factory()->percent(10)->create(['role_id' => $role->getKey()]);

        (new SaveTariffAdjustments)->save(TariffSchedule::factory()->draft()->create(), [$role->getKey() => null]);

        $this->assertModelExists($other);
    }
}
