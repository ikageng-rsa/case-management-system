<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\Billing\BillKind;
use App\Enums\Billing\BillLineKind;
use App\Enums\Billing\BillStatus;
use App\Enums\Billing\FeeAgreementKind;
use App\Enums\Billing\FeeAgreementStatus;
use App\Models\Billing\Bill;
use App\Models\Billing\BillLine;
use App\Models\Billing\FeeAgreement;
use App\Models\User\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class DraftFeeBill
{
    public function __construct(
        protected TotalBill $totalBill,
    ) {}

    /**
     * Draw a draft fee bill from a finalised contingency or estate agreement.
     * It has one line: the firm's agreed share of what the claim paid out or
     * the estate came to. An agreement is billed once, unless its bill is
     * voided or the draft deleted.
     */
    public function draft(FeeAgreement $agreement, string $addressee, User $draftedBy, ?string $address = null): Bill
    {
        if ($agreement->status !== FeeAgreementStatus::Finalised) {
            throw new BillRefused(['Finalise the agreement with the amount received before billing the fee.']);
        }

        if (! $agreement->fee()) {
            throw new BillRefused(['There is no fee to bill: the agreed share of the amount received comes to nothing.']);
        }

        $claim = BillLine::claimFor(BillKind::AgreementFee, BillLineKind::AgreementFee, $agreement->getKey());

        if (BillLine::query()->where('claim', $claim)->exists()) {
            throw new BillRefused(['This agreement already has a fee bill.']);
        }

        try {
            return DB::transaction(fn (): Bill => $this->save($agreement, $addressee, $address, $draftedBy, $claim));
        } catch (QueryException $e) {
            // Two fee bills drawn at once: the claim on the agreement is unique, so the second loses.
            throw new BillRefused(['This agreement already has a fee bill.']);
        }
    }

    protected function save(FeeAgreement $agreement, string $addressee, ?string $address, User $draftedBy, string $claim): Bill
    {
        $bill = $agreement->matter->bills()->make([
            'kind' => BillKind::AgreementFee,
            'status' => BillStatus::Draft,
            'addressee' => trim($addressee),
            'addressee_address' => filled($address) ? trim($address) : null,
        ]);
        $bill->forceFill(['drafted_by' => $draftedBy->getKey()])->save();

        $bill->lines()->create([
            'kind' => BillLineKind::AgreementFee,
            'position' => 0,
            'occurred_on' => ($agreement->closed_at ?? now())->toDateString(),
            'description' => $this->description($agreement),
            'units' => 1,
            'rate_cents' => $agreement->fee(),
            'fee_cents' => $agreement->fee(),
            'fee_agreement_id' => $agreement->getKey(),
            'claim' => $claim,
        ]);

        return $this->totalBill->total($bill);
    }

    /** e.g. "25% of R 480 000.00 received, under the contingency fee agreement". */
    protected function description(FeeAgreement $agreement): string
    {
        $percent = rtrim(rtrim(number_format($agreement->percent(), 2, '.', ''), '0'), '.');
        $amount = 'R '.number_format($agreement->amount_received_cents / 100, 2, '.', ' ');

        if ($agreement->kind === FeeAgreementKind::Estate) {
            return "{$percent}% of the estate funds of {$amount}, under the estate fee agreement";
        }

        return "{$percent}% of {$amount} received, under the contingency fee agreement";
    }
}
