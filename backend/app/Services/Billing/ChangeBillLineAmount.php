<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\Billing\BillLine;
use App\Models\User\User;
use Illuminate\Support\Facades\DB;

class ChangeBillLineAmount
{
    public function __construct(
        protected TotalBill $totalBill,
    ) {}

    /**
     * Charge a different amount for a line on a draft, such as writing part of
     * a fee off. The amount it was drawn at is kept, with who changed it, and
     * setting it back to that amount clears the change.
     */
    public function change(BillLine $line, int $amountCents, User $by): BillLine
    {
        $bill = $line->bill;

        if (! $bill->isDraft()) {
            throw new BillRefused(['Only a draft can be changed.']);
        }

        return DB::transaction(function () use ($line, $bill, $amountCents, $by): BillLine {
            $before = $line->amount();
            $original = $line->original_amount_cents ?? $before;
            $column = $line->kind->isFee() ? 'fee_cents' : 'disbursement_cents';

            $line->forceFill([
                $column => $amountCents,
                'original_amount_cents' => $amountCents === $original ? null : $original,
                'overridden_by' => $amountCents === $original ? null : $by->getKey(),
            ])->save();

            $this->totalBill->total($bill);

            $bill->recordEvent('line_changed', "{$by->name} changed a line on {$bill->auditLabel()}", [
                'description' => $line->description,
                'from_cents' => $before,
                'to_cents' => $amountCents,
            ]);

            return $line;
        });
    }
}
