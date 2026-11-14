<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\Billing\FeeAgreementStatus;
use App\Models\Billing\FeeAgreement;

class FinaliseFeeAgreement
{
    /**
     * Close a contingency or estate agreement with what the claim paid out or
     * the estate came to. The firm's fee is its share of that amount.
     */
    public function finalise(FeeAgreement $agreement, int $amountReceivedCents): FeeAgreement
    {
        if (! $agreement->isActive()) {
            throw new FeeAgreementRefused('Only an agreement still in place can be finalised.');
        }

        if (! $agreement->kind->isPercentage()) {
            throw new FeeAgreementRefused('A mandate is billed by statement, so there is no amount to finalise it with.');
        }

        $agreement->update([
            'status' => FeeAgreementStatus::Finalised,
            'amount_received_cents' => $amountReceivedCents,
            'closed_at' => now(),
        ]);

        return $agreement;
    }
}
