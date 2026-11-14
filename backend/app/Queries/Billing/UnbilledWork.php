<?php

declare(strict_types=1);

namespace App\Queries\Billing;

use App\Enums\Billing\BillKind;
use App\Enums\Billing\BillLineKind;
use App\Models\Billing\BillLine;
use App\Models\Matter\Matter;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/*
 * The billable work and the disbursements on a matter that no live bill of a
 * kind has claimed yet, within a period when one is given. A bill that is voided
 * gives its claims up, so its work shows here again. Work logged late for an
 * earlier month is still unbilled, so it is picked up by the next bill.
 */
class UnbilledWork
{
    /** @return array{narrations: Collection, disbursements: Collection} */
    public function for(Matter $matter, BillKind $kind, ?CarbonInterface $from = null, ?CarbonInterface $to = null): array
    {
        $unclaimed = fn (BillLineKind $line) => fn (Builder $query) => $query->where('claim', 'like', BillLine::claimFor($kind, $line, '').'%');

        $narrations = $matter->narrations()
            ->whereHas('activityType', fn (Builder $query) => $query->billable())
            ->whereDoesntHave('billLines', $unclaimed(BillLineKind::Work))
            ->when($from, fn (Builder $query) => $query->where('occurred_at', '>=', $from->copy()->startOfDay()))
            ->when($to, fn (Builder $query) => $query->where('occurred_at', '<=', $to->copy()->endOfDay()))
            ->with(['activityType', 'author.roles'])
            ->orderBy('occurred_at')
            ->get()
            ->each->setRelation('matter', $matter);

        $disbursements = $matter->disbursements()
            ->whereDoesntHave('billLines', $unclaimed(BillLineKind::Disbursement))
            ->when($from, fn (Builder $query) => $query->whereDate('incurred_on', '>=', $from))
            ->when($to, fn (Builder $query) => $query->whereDate('incurred_on', '<=', $to))
            ->orderBy('incurred_on')
            ->get();

        return ['narrations' => $narrations, 'disbursements' => $disbursements];
    }
}
