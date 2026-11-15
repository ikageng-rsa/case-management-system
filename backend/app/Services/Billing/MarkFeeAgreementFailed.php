<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\Billing\FeeAgreementKind;
use App\Enums\Billing\FeeAgreementStatus;
use App\Models\Billing\FeeAgreement;

class MarkFeeAgreementFailed
{
    /**
     * A contingency claim that did not succeed earns no share. Closing it lets
     * a mandate be recorded, so the client can be billed by statement instead.
     */
    public function fail(FeeAgreement $agreement): FeeAgreement
    {
        if (! $agreement->isActive()) {
            throw new FeeAgreementRefused('Only an agreement still in place can be marked failed.');
        }

        if ($agreement->kind !== FeeAgreementKind::Contingency) {
            throw new FeeAgreementRefused('Only a contingency claim can fail.');
        }

        $agreement->update(['status' => FeeAgreementStatus::Failed, 'closed_at' => now()]);

        return $agreement;
    }
}
