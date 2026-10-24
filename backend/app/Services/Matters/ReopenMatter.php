<?php

declare(strict_types=1);

namespace App\Services\Matters;

use App\Models\Matter\Matter;
use Illuminate\Support\Facades\Auth;

class ReopenMatter
{
    /**
     * Closing is not undone lightly, so reopening is its own permission and says
     * why. The reason and the date it had been closed since go in the log.
     */
    public function reopen(Matter $matter, string $reason): Matter
    {
        if ($matter->isOpen()) {
            return $matter;
        }

        $closedAt = $matter->closed_at;

        $matter->closed_at = null;
        $matter->save();

        $matter->recordEvent(
            'reopened',
            sprintf('%s reopened matter %s', Auth::user()?->name ?? 'System', $matter->reference),
            ['reason' => trim($reason), 'was_closed_since' => $closedAt->toIso8601String()],
        );

        return $matter;
    }
}
