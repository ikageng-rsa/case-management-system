<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\Billing\BillLineKind;
use App\Models\Billing\Bill;
use App\Models\Billing\BillLine;
use App\Models\User\User;
use App\Queries\Billing\UnbilledWork;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class AddWorkToBill
{
    public function __construct(
        protected UnbilledWork $unbilled,
        protected BillLines $billLines,
        protected TotalBill $totalBill,
    ) {}

    /**
     * Add work and disbursements the matter has recorded but not yet billed on
     * a bill of this kind to a draft, whether they were left off it, taken off
     * it, or recorded since it was drawn. They are priced as the draft was, on
     * its scale, and time on top uses the rate already on the bill for that
     * person unless a rate is given. Anything already billed is left alone.
     *
     * @param  list<string>  $narrationIds
     * @param  list<int>  $disbursementIds
     * @param  array<string, int>  $timeRates  cents an hour, by person
     */
    public function add(Bill $bill, array $narrationIds, array $disbursementIds, User $by, array $timeRates = []): Bill
    {
        if (! $bill->isDraft()) {
            throw new BillRefused(['Only a draft can be changed.']);
        }

        ['narrations' => $narrations, 'disbursements' => $disbursements] = $this->unbilled->for($bill->matter, $bill->kind);
        $narrations = $narrations->whereIn('id', $narrationIds)->values();
        $disbursements = $disbursements->whereIn('id', $disbursementIds)->values();

        if ($narrations->isEmpty() && $disbursements->isEmpty()) {
            throw new BillRefused(['Choose the work or disbursements to add. What was chosen is already billed.']);
        }

        $lines = $this->billLines->for(
            $bill->kind,
            $narrations,
            $disbursements,
            $bill->scale,
            $timeRates + $this->ratesOnBill($bill),
            (int) $bill->lines()->max('position') + 1,
        );

        try {
            return DB::transaction(function () use ($bill, $lines, $by): Bill {
                $bill->lines()->createMany($lines);
                $this->renumber($bill);

                $bill->recordEvent('lines_added', "{$by->name} added ".count($lines)." line(s) to {$bill->auditLabel()}", [
                    'descriptions' => array_column($lines, 'description'),
                ]);

                return $this->totalBill->total($bill);
            });
        } catch (QueryException $e) {
            throw new BillRefused(['Some of this work was billed by someone else a moment ago. Try again.']);
        }
    }

    /** @return array<string, int> the hourly rate each person's time is already charged at on the bill */
    protected function ratesOnBill(Bill $bill): array
    {
        return $bill->lines()->where('kind', BillLineKind::TimeOnTop)->pluck('rate_cents', 'user_id')->all();
    }

    /* The bill reads in date order, so the added lines take their places among the others. */
    protected function renumber(Bill $bill): void
    {
        $bill->lines()->reorder()->orderBy('occurred_on')->orderBy('position')->get()
            ->each(fn (BillLine $line, int $index) => $line->update(['position' => $index]));
    }
}
