<?php

declare(strict_types=1);

namespace App\Queries\Narrations;

use App\Enums\Narration\ActivityMeasure;
use App\Models\Matter\Matter;
use App\Models\Narration\Narration;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Builder;

/*
 * The billable time on the ledger, in hours, and how many entries it comes
 * from. Someone who may not see every matter sees only their own time.
 * Pages, kilometres and items are billed by the unit, not the hour, so they
 * are left out.
 */
class UnbilledTime
{
    /** @return array<string, mixed> */
    public function for(User $user): array
    {
        $narrations = Narration::query()
            ->whereHas(
                'activityType',
                fn (Builder $query) => $query->billable()->where('measure', ActivityMeasure::Minutes),
            )
            ->when(! $user->can('viewAny', Matter::class), fn (Builder $query) => $query->by($user))
            ->get(['quantity']);

        return [
            'unbilledHours' => (float) $narrations->sum('quantity') / 60,
            'unbilledEntriesCount' => $narrations->count(),
        ];
    }
}
