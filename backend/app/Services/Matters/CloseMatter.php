<?php

declare(strict_types=1);

namespace App\Services\Matters;

use App\Models\Matter\Matter;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;

class CloseMatter
{
    public function close(Matter $matter, ?CarbonInterface $closedAt = null): Matter
    {
        if ($matter->isClosed()) {
            return $matter;
        }

        $matter->closed_at = $closedAt ?? now();
        $matter->save();

        // Recorded as what it is. The closing date is kept off the model's own field log,
        // so this is the one entry for it, not one for the field and one for the act.
        $matter->recordEvent(
            'closed',
            sprintf('%s closed matter %s', Auth::user()?->name ?? 'System', $matter->reference),
            ['closed_at' => $matter->closed_at->toIso8601String()],
        );

        return $matter;
    }
}
