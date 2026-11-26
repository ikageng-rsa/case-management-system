<?php

declare(strict_types=1);

namespace App\Queries\Narrations;

use App\Enums\Auth\Permission;
use App\Enums\Narration\ActivityMeasure;
use App\Models\Narration\Narration;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Builder;

/* Billable time not yet on a bill, in hours: the firm's for those who see bills, otherwise the person's own. */
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
            ->whereDoesntHave('billLines', fn (Builder $query) => $query->whereNotNull('claim'))
            ->when(
                $user->can(Permission::ViewBills->value),
                fn (Builder $query) => $query->whereHas('matter', fn (Builder $matters) => $matters->withBillingReachedBy($user)),
                fn (Builder $query) => $query->by($user),
            )
            ->get(['quantity']);

        return [
            'unbilledHours' => (float) $narrations->sum('quantity') / 60,
            'unbilledEntriesCount' => $narrations->count(),
        ];
    }
}
