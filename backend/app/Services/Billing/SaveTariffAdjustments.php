<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\Billing\TariffAdjustmentKind;
use App\Models\Billing\TariffSchedule;
use Illuminate\Support\Facades\DB;

class SaveTariffAdjustments
{
    /**
     * Set what each role charges against a tariff. A role given no markup
     * charges the gazette rate. Bills keep the rate they were drawn at, so a
     * change here prices only what is billed after it.
     *
     * @param  array<int, array{kind: TariffAdjustmentKind, value: int}|null>  $adjustments  by role id; null clears the role's markup
     */
    public function save(TariffSchedule $schedule, array $adjustments): void
    {
        DB::transaction(function () use ($schedule, $adjustments): void {
            foreach ($adjustments as $roleId => $adjustment) {
                $existing = $schedule->adjustments()->where('role_id', $roleId)->first();

                if ($adjustment === null) {
                    $existing?->delete();

                    continue;
                }

                $existing
                    ? $existing->update($adjustment)
                    : $schedule->adjustments()->create(['role_id' => $roleId, ...$adjustment]);
            }
        });
    }
}
