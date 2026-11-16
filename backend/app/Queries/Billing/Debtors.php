<?php

declare(strict_types=1);

namespace App\Queries\Billing;

use App\Enums\Billing\BillKind;
use App\Enums\Billing\BillStatus;
use App\Models\Billing\Bill;
use App\Models\User\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/*
 * What is owed to the firm: every issued bill with a balance, on the matters a
 * person may see, aged by how long ago it was issued. What clients owe on
 * statements and fee bills is kept apart from what the other side owes on bills
 * of cost, because different people owe them. Work not yet billed is never
 * counted here: nothing is owed until a bill is issued.
 */
class Debtors
{
    /** The ageing columns, by the most days since issue each one holds; the last has no limit. */
    public const BUCKETS = ['0–30 days' => 30, '31–60 days' => 60, '61–90 days' => 90, 'Over 90 days' => null];

    /** @return array<string, mixed> */
    public function for(User $user, ?CarbonInterface $asAt = null): array
    {
        $asAt ??= now();

        $rows = Bill::query()
            ->where('status', BillStatus::Issued)
            ->whereColumn('paid_cents', '<', 'total_cents')
            ->whereHas('matter', fn (Builder $matters) => $matters->withBillingReachedBy($user))
            ->with('matter.client')
            ->orderBy('issued_at')
            ->get()
            ->map(function (Bill $bill) use ($asAt): array {
                $days = (int) $bill->issued_at->copy()->startOfDay()->diffInDays($asAt->copy()->startOfDay());

                return ['bill' => $bill, 'days' => $days, 'bucket' => $this->bucket($days), 'balanceCents' => $bill->balanceCents()];
            });

        [$otherSide, $clients] = $rows->partition(fn (array $row) => $row['bill']->kind === BillKind::BillOfCost);

        return [
            'buckets' => array_keys(self::BUCKETS),
            'owedByClients' => $this->section($clients->values()),
            'owedOnBillsOfCost' => $this->section($otherSide->values()),
        ];
    }

    protected function bucket(int $days): string
    {
        foreach (self::BUCKETS as $label => $upTo) {
            if ($upTo === null || $days <= $upTo) {
                return $label;
            }
        }

        return array_key_last(self::BUCKETS);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array{rows: Collection<int, array<string, mixed>>, totalCents: int, bucketCents: array<string, int>}
     */
    protected function section(Collection $rows): array
    {
        return [
            'rows' => $rows,
            'totalCents' => (int) $rows->sum('balanceCents'),
            'bucketCents' => collect(array_keys(self::BUCKETS))
                ->mapWithKeys(fn (string $label) => [$label => (int) $rows->where('bucket', $label)->sum('balanceCents')])
                ->all(),
        ];
    }
}
