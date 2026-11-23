<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\Billing\FirmAccount;
use App\Enums\Billing\PaymentMethod;
use App\Models\Billing\Bill;
use App\Models\Billing\Payment;
use App\Models\User\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class RecordPayment
{
    public function __construct(
        protected TallyPayments $tally,
    ) {}

    /**
     * Record money received on an issued bill, with its proof of payment. A
     * payment is never more than what is still owed on the bill. The bill is
     * locked while it is recorded, so two payments taken at once cannot
     * together overpay it.
     *
     * @param  array{amount_cents: int, received_on: string, method: PaymentMethod, account: FirmAccount, reference?: ?string}  $data
     */
    public function record(Bill $bill, array $data, User $recordedBy, UploadedFile $proof): Payment
    {
        return DB::transaction(function () use ($bill, $data, $recordedBy, $proof): Payment {
            $bill = Bill::query()->lockForUpdate()->findOrFail($bill->getKey());

            if (! $bill->isIssued()) {
                throw new PaymentRefused('Only an issued bill can be paid. A draft is issued first, and nothing is owed on a voided bill.');
            }

            $this->tally->tally($bill);

            if ($bill->balanceCents() === 0) {
                throw new PaymentRefused('This bill is settled. Nothing more is owed on it.');
            }

            if ($data['amount_cents'] > $bill->balanceCents()) {
                throw new PaymentRefused('That is more than the '.$this->rand($bill->balanceCents()).' still owed on this bill.');
            }

            $payment = $bill->payments()->make([...$data, 'reference' => filled($data['reference'] ?? null) ? trim($data['reference']) : null]);
            $payment->recordedBy()->associate($recordedBy);
            $payment->save();
            $payment->attachProof($proof);

            $this->tally->tally($bill);

            return $payment->setRelation('bill', $bill);
        });
    }

    protected function rand(int $cents): string
    {
        return 'R '.number_format($cents / 100, 2, '.', ' ');
    }
}
