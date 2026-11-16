<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\Billing\Bill;
use App\Models\Billing\Payment;
use App\Models\User\User;
use Illuminate\Support\Facades\DB;

class ReversePayment
{
    public function __construct(
        protected TallyPayments $tally,
    ) {}

    /**
     * Take back a payment recorded in error, such as one put on the wrong bill
     * or for the wrong amount. It stays on record with the reason and its
     * proof, and no longer counts towards what the bill has been paid.
     */
    public function reverse(Payment $payment, User $by, string $reason): Payment
    {
        if ($payment->isReversed()) {
            throw new PaymentRefused('This payment has already been reversed.');
        }

        return DB::transaction(function () use ($payment, $by, $reason): Payment {
            $bill = Bill::query()->lockForUpdate()->findOrFail($payment->bill_id);

            $payment->forceFill(['reversed_by' => $by->getKey()]);
            $payment->update(['reversed_at' => now(), 'reverse_reason' => trim($reason)]);

            $this->tally->tally($bill);

            return $payment->setRelation('bill', $bill);
        });
    }
}
