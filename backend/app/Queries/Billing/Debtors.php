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

/* Issued bills with a balance, aged by days since issue; clients and bills of cost apart. */
class Debtors
{
    /** Ageing columns by their upper limit in days; null has none. */
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
