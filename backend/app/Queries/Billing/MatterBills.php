<?php

declare(strict_types=1);

namespace App\Queries\Billing;

use App\Enums\Billing\BillKind;
use App\Enums\Billing\TariffScale;
use App\Models\Matter\Matter;
use App\Models\Narration\Narration;

/*
 * A matter's bills for its billing tab, newest first, and what raising a new
 * one needs: who it can be addressed to, the scales, and the people whose time
 * on top is still unbilled, so a rate can be asked for each of them.
 */
class MatterBills
{
    public function __construct(
        protected UnbilledWork $unbilled,
    ) {}

    /** @return array<string, mixed> */
    public function for(Matter $matter): array
    {
        $unbilledTime = $this->unbilled->for($matter, BillKind::Statement)['narrations']
            ->merge($this->unbilled->for($matter, BillKind::BillOfCost)['narrations'])
            ->filter(fn (Narration $narration) => $narration->minutes > 0)
            ->unique('id');

        return [
            'bills' => $matter->bills()->latest()->latest('id')->get(),
            'billKinds' => $matter->matterType->is_litigation ? [BillKind::Statement, BillKind::BillOfCost] : [BillKind::Statement],
            'addressees' => collect([$matter->client->full_name])->merge($matter->parties()->pluck('name'))->unique()->values(),
            'scales' => TariffScale::cases(),
            'timeOnTop' => $unbilledTime->groupBy('author_id')->map(fn ($narrations) => [
                'user' => $narrations->first()->author,
                'minutes' => (int) $narrations->sum('minutes'),
            ])->values(),
        ];
    }
}
