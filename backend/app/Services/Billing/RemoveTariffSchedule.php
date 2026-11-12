<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\Billing\TariffSchedule;
use Illuminate\Support\Facades\DB;

class RemoveTariffSchedule
{
    /**
     * A schedule can be taken back until its date arrives. Once it is in force
     * its rates may have been charged, so it stays on record, and a newer
     * schedule replaces it instead.
     */
    public function remove(TariffSchedule $schedule): void
    {
        if ($schedule->hasStarted()) {
            throw new TariffScheduleHasStarted;
        }

        DB::transaction(function () use ($schedule): void {
            $schedule->clearMediaCollection(TariffSchedule::SOURCE);
            $schedule->delete();
        });
    }
}
