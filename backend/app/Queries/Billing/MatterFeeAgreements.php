<?php

declare(strict_types=1);

namespace App\Queries\Billing;

use App\Enums\Billing\FeeAgreementKind;
use App\Models\Matter\Matter;
use App\Settings\BillingSettings;

/*
 * A matter's fee agreements for its billing tab, newest first, the one in
 * place, and what a new one would start from: the matter type's kind and the
 * firm's default share for each kind.
 */
class MatterFeeAgreements
{
    public function __construct(
        protected BillingSettings $settings,
    ) {}

    /** @return array<string, mixed> */
    public function for(Matter $matter): array
    {
        $agreements = $matter->feeAgreements()->with(['recordedBy', 'media'])->latest('id')->get();

        return [
            'feeAgreements' => $agreements,
            'activeAgreement' => $agreements->first->isActive(),
            'suggestedFeeKind' => $matter->matterType->default_fee_kind,
            'defaultPercents' => [
                FeeAgreementKind::Contingency->value => $this->settings->contingencyPercent,
                FeeAgreementKind::Estate->value => $this->settings->estatePercent,
            ],
            'feeKinds' => FeeAgreementKind::cases(),
        ];
    }
}
