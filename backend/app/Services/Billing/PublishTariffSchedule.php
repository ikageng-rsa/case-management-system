<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\Billing\TariffSchedule;

class PublishTariffSchedule
{
    /** Put a checked draft to use: from its date, bills are priced from it. */
    public function publish(TariffSchedule $schedule): void
    {
        if ($schedule->isPublished()) {
            return;
        }

        $schedule->update(['published_at' => now()]);
    }
}
