<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\Billing\BillKind;
use App\Enums\Billing\BillLineKind;
use App\Enums\Billing\TariffScale;
use App\Models\Billing\BillLine;
use App\Models\Billing\Disbursement;
use App\Models\Narration\Narration;
use App\Queries\Billing\NarrationPrice;
use Illuminate\Support\Collection;

/*
 * Turns work and disbursements into the lines of a bill of a kind: each
 * narration priced on the tariff, its time on top at the person's hourly rate,
 * and each disbursement at cost, every line claiming what it bills. Used to draw
 * a bill and to add work to a draft, so both price work the same way.
 */
class BillLines
{
    public function __construct(
        protected NarrationPrice $price,
    ) {}

    /**
     * The lines in date order, numbered from the first position given, or
     * every reason they cannot be drawn.
     *
     * @param  Collection<int, Narration>  $narrations
     * @param  Collection<int, Disbursement>  $disbursements
     * @param  array<string, int>  $timeRates  cents an hour, by person
     * @return list<array<string, mixed>>
     */
    public function for(BillKind $kind, Collection $narrations, Collection $disbursements, ?TariffScale $scale, array $timeRates, int $firstPosition = 0): array
    {
        $lines = [];
        $problems = [];

        if ($narrations->isNotEmpty() && $scale === null) {
            throw new BillRefused(['Choose the tariff scale to bill on. The matter has none set.']);
        }

        foreach ($narrations as $narration) {
            $price = $this->price->for($narration, $scale);

            if (! $price['priced']) {
                $problems[] = $narration->occurred_at->format('j M Y').', '.$narration->activityType->name.': '.$price['reason'];

                continue;
            }

            $lines[] = [
                'kind' => BillLineKind::Work,
                'occurred_on' => $narration->occurred_at->toDateString(),
                'item' => $narration->activityType->code,
                'description' => $narration->body,
                'units' => $price['units'],
                'rate_cents' => $price['rateCents'],
                'fee_cents' => $price['amountCents'],
                'narration_id' => $narration->getKey(),
                'user_id' => $narration->author_id,
                'claim' => BillLine::claimFor($kind, BillLineKind::Work, $narration->getKey()),
            ];

            if (! $narration->minutes) {
                continue;
            }

            if (! isset($timeRates[$narration->author_id])) {
                $problems[] = "Give an hourly rate for {$narration->author->name}'s time on top.";

                continue;
            }

            $lines[] = [
                'kind' => BillLineKind::TimeOnTop,
                'occurred_on' => $narration->occurred_at->toDateString(),
                'item' => $narration->activityType->code,
                'description' => "Time: {$narration->body}",
                'units' => round($narration->minutes / 60, 2),
                'rate_cents' => $timeRates[$narration->author_id],
                'fee_cents' => (int) round($narration->minutes * $timeRates[$narration->author_id] / 60),
                'narration_id' => $narration->getKey(),
                'user_id' => $narration->author_id,
                'claim' => BillLine::claimFor($kind, BillLineKind::TimeOnTop, $narration->getKey()),
            ];
        }

        if ($problems !== []) {
            throw new BillRefused(array_values(array_unique($problems)));
        }

        foreach ($disbursements as $disbursement) {
            $lines[] = [
                'kind' => BillLineKind::Disbursement,
                'occurred_on' => $disbursement->incurred_on->toDateString(),
                'description' => $disbursement->description,
                'units' => 1,
                'disbursement_cents' => $disbursement->amount_cents,
                'disbursement_id' => $disbursement->getKey(),
                'claim' => BillLine::claimFor($kind, BillLineKind::Disbursement, $disbursement->getKey()),
            ];
        }

        usort($lines, fn (array $a, array $b) => $a['occurred_on'] <=> $b['occurred_on']);

        return array_map(fn (array $line, int $index) => [...$line, 'position' => $firstPosition + $index], $lines, array_keys($lines));
    }
}
