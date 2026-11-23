<?php

declare(strict_types=1);

namespace App\Queries\Billing;

use App\Enums\Billing\BillKind;
use App\Enums\Billing\TariffScale;
use App\Models\Billing\Bill;
use App\Models\Matter\Matter;
use App\Models\Narration\Narration;

/*
 * A matter's bills for its billing tab, newest first, what is still owed on the
 * issued ones (by the client on statements and fee bills, and by the other side
 * on bills of cost, kept apart because different people owe them), and what raising a new
 * one needs: who it can be addressed to, the scales, and the people whose time
 * on top is still unbilled, so a rate can be asked for each of them. The
 * client's address on file is given only for someone cleared to see client
 * data unmasked; for anyone else a statement still goes to it when it is drawn.
 */
class MatterBills
{
    public function __construct(
        protected UnbilledWork $unbilled,
    ) {}

    /** @return array<string, mixed> */
    public function for(Matter $matter, bool $withClientAddress = false): array
    {
        $unbilledTime = $this->unbilled->for($matter, BillKind::Statement)['narrations']
            ->merge($this->unbilled->for($matter, BillKind::BillOfCost)['narrations'])
            ->filter(fn (Narration $narration) => $narration->minutes > 0)
            ->unique('id');

        $bills = $matter->bills()->latest()->latest('id')->get();
        $owed = fn (bool $byOtherSide) => (int) $bills
            ->filter(fn (Bill $bill) => $bill->isIssued() && ($bill->kind === BillKind::BillOfCost) === $byOtherSide)
            ->sum(fn (Bill $bill) => $bill->balanceCents());

        return [
            'bills' => $bills,
            'owedByClientCents' => $owed(false),
            'owedByOtherSideCents' => $owed(true),
            'billKinds' => $matter->matterType->is_litigation ? [BillKind::Statement, BillKind::BillOfCost] : [BillKind::Statement],
            'addressees' => collect([$matter->client->full_name])->merge($matter->parties()->pluck('name'))->unique()->values(),
            'clientAddress' => $withClientAddress ? $matter->client->addressOnFile() : null,
            'scales' => TariffScale::cases(),
            'timeOnTop' => $unbilledTime->groupBy('author_id')->map(fn ($narrations) => [
                'user' => $narrations->first()->author,
                'minutes' => (int) $narrations->sum('minutes'),
            ])->values(),
        ];
    }
}
