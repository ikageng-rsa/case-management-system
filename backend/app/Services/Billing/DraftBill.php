<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\Billing\BillKind;
use App\Enums\Billing\BillLineKind;
use App\Enums\Billing\BillStatus;
use App\Enums\Billing\FeeAgreementKind;
use App\Enums\Billing\TariffScale;
use App\Models\Billing\Bill;
use App\Models\Billing\BillLine;
use App\Models\Billing\Disbursement;
use App\Models\Matter\Matter;
use App\Models\Narration\Narration;
use App\Models\User\User;
use App\Queries\Billing\NarrationPrice;
use App\Queries\Billing\UnbilledWork;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DraftBill
{
    public function __construct(
        protected UnbilledWork $unbilled,
        protected NarrationPrice $price,
        protected TotalBill $totalBill,
    ) {}

    /**
     * Draw a draft statement or bill of cost from the work and disbursements on
     * the matter that no live bill of its kind has claimed, within the period.
     * The work is priced on the tariff in force when it was done, on the given
     * scale or else the matter's. Time on top is charged at the hourly rate
     * given for each person. The drawing fee is added under fees. Nothing is
     * drawn while any of it cannot be priced: every reason is given instead.
     *
     * @param  array<string, int>  $timeRates  cents an hour, by the id of the person whose time it is
     */
    public function draft(
        Matter $matter,
        BillKind $kind,
        string $addressee,
        User $draftedBy,
        ?TariffScale $scale = null,
        ?CarbonInterface $from = null,
        ?CarbonInterface $to = null,
        array $timeRates = [],
    ): Bill {
        $this->guardKind($matter, $kind);

        $scale ??= $matter->tariff_scale;
        ['narrations' => $narrations, 'disbursements' => $disbursements] = $this->unbilled->for($matter, $kind, $from, $to);

        if ($narrations->isEmpty() && $disbursements->isEmpty()) {
            throw new BillRefused(['There is no unbilled work or disbursement on this matter for that period.']);
        }

        $lines = $this->lines($kind, $narrations, $disbursements, $scale, $timeRates);

        try {
            return DB::transaction(fn (): Bill => $this->save($matter, $kind, $addressee, $draftedBy, $scale, $from, $to, $lines));
        } catch (QueryException $e) {
            // Two bills drawn at once: the claim on the work is unique, so the second loses.
            throw new BillRefused(['Some of this work was billed by someone else a moment ago. Draw the bill again.']);
        }
    }

    /*
     * A statement bills the client, so the client must have agreed to be billed
     * by statement. A bill of cost is drawn on the court's tariff, so the matter
     * has to be before a court.
     */
    protected function guardKind(Matter $matter, BillKind $kind): void
    {
        $agreement = $matter->feeAgreements()->active()->first();

        if ($kind === BillKind::Statement && $agreement?->kind !== FeeAgreementKind::Mandate) {
            throw new BillRefused([$agreement === null
                ? 'Record a mandate on this matter before billing the client by statement.'
                : "The client is charged under a {$agreement->kind->label()} agreement, not by statement."]);
        }

        if ($kind === BillKind::BillOfCost && ! $matter->matterType->is_litigation) {
            throw new BillRefused(['A bill of cost is for a matter before a court.']);
        }

        if ($kind === BillKind::AgreementFee) {
            throw new BillRefused(['A fee bill is drawn from a finalised fee agreement.']);
        }
    }

    /**
     * The bill's lines in date order, or every reason it cannot be drawn.
     *
     * @param  Collection<int, Narration>  $narrations
     * @param  Collection<int, Disbursement>  $disbursements
     * @param  array<string, int>  $timeRates
     * @return list<array<string, mixed>>
     */
    protected function lines(BillKind $kind, Collection $narrations, Collection $disbursements, ?TariffScale $scale, array $timeRates): array
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

        return array_map(fn (array $line, int $position) => [...$line, 'position' => $position], $lines, array_keys($lines));
    }

    /** @param  list<array<string, mixed>>  $lines */
    protected function save(Matter $matter, BillKind $kind, string $addressee, User $draftedBy, ?TariffScale $scale, ?CarbonInterface $from, ?CarbonInterface $to, array $lines): Bill
    {
        $bill = $matter->bills()->make([
            'kind' => $kind,
            'status' => BillStatus::Draft,
            'addressee' => trim($addressee),
            'scale' => $scale,
            'period_from' => $from?->toDateString(),
            'period_to' => $to?->toDateString(),
        ]);
        $bill->forceFill(['drafted_by' => $draftedBy->getKey()])->save();

        $bill->lines()->createMany($lines);

        return $this->totalBill->total($bill);
    }
}
