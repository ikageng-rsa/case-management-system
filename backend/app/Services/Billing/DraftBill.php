<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\Billing\BillKind;
use App\Enums\Billing\BillStatus;
use App\Enums\Billing\FeeAgreementKind;
use App\Enums\Billing\TariffScale;
use App\Enums\Client\ContactKind;
use App\Models\Billing\Bill;
use App\Models\Matter\Matter;
use App\Models\User\User;
use App\Queries\Billing\UnbilledWork;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class DraftBill
{
    public function __construct(
        protected UnbilledWork $unbilled,
        protected BillLines $billLines,
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
        ?string $address = null,
    ): Bill {
        $this->guardKind($matter, $kind);

        $scale ??= $matter->tariff_scale;
        ['narrations' => $narrations, 'disbursements' => $disbursements] = $this->unbilled->for($matter, $kind, $from, $to);

        if ($narrations->isEmpty() && $disbursements->isEmpty()) {
            throw new BillRefused(['There is no unbilled work or disbursement on this matter for that period.']);
        }

        $lines = $this->billLines->for($kind, $narrations, $disbursements, $scale, $timeRates);

        try {
            $address = filled($address) ? trim($address) : $this->clientAddress($matter, $kind);

            return DB::transaction(fn (): Bill => $this->save($matter, $kind, $addressee, $address, $draftedBy, $scale, $from, $to, $lines));
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

    /** @param  list<array<string, mixed>>  $lines */
    /*
     * A statement goes to the client, so without an address given it is sent to
     * the one on file: the postal address, or else the physical one, primary
     * first. Read here rather than shown on the form, so drawing a bill does not
     * reveal an address to someone who may only see it masked.
     */
    protected function clientAddress(Matter $matter, BillKind $kind): ?string
    {
        if ($kind !== BillKind::Statement) {
            return null;
        }

        return $matter->client->contacts()
            ->whereIn('kind', [ContactKind::Postal, ContactKind::Physical])
            ->get()
            ->sortBy(fn ($contact) => [$contact->kind === ContactKind::Postal ? 0 : 1, $contact->is_primary ? 0 : 1])
            ->first()?->value;
    }

    protected function save(Matter $matter, BillKind $kind, string $addressee, ?string $address, User $draftedBy, ?TariffScale $scale, ?CarbonInterface $from, ?CarbonInterface $to, array $lines): Bill
    {
        $bill = $matter->bills()->make([
            'kind' => $kind,
            'status' => BillStatus::Draft,
            'addressee' => trim($addressee),
            'addressee_address' => $address,
            'scale' => $scale,
            'period_from' => $from?->toDateString(),
            'period_to' => $to?->toDateString(),
        ]);
        $bill->forceFill(['drafted_by' => $draftedBy->getKey()])->save();

        $bill->lines()->createMany($lines);

        return $this->totalBill->total($bill);
    }
}
