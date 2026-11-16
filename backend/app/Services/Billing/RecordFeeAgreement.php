<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\Billing\FeeAgreementKind;
use App\Enums\Billing\FeeAgreementStatus;
use App\Models\Billing\FeeAgreement;
use App\Models\Matter\Matter;
use App\Models\User\User;
use App\Settings\BillingSettings;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class RecordFeeAgreement
{
    public function __construct(
        protected BillingSettings $settings,
    ) {}

    /**
     * Record how the client is charged on a matter. A matter is charged under
     * one agreement at a time, so the one in place has to fail or be finalised
     * first. A share left empty is the firm's current default for the kind, and
     * a mandate carries none.
     */
    public function record(
        Matter $matter,
        FeeAgreementKind $kind,
        User $recordedBy,
        ?float $percent = null,
        ?CarbonInterface $signedOn = null,
        ?UploadedFile $signedCopy = null,
    ): FeeAgreement {
        if ($matter->feeAgreements()->active()->exists()) {
            throw new FeeAgreementRefused('This matter already has a fee agreement in place. Mark it failed or finalise it before recording another.');
        }

        return DB::transaction(function () use ($matter, $kind, $recordedBy, $percent, $signedOn, $signedCopy): FeeAgreement {
            $agreement = $matter->feeAgreements()->make([
                'kind' => $kind,
                'status' => FeeAgreementStatus::Active,
                'percent_hundredths' => $kind->isPercentage() ? (int) round(($percent ?? $this->defaultPercent($kind)) * 100) : null,
                'signed_on' => $signedOn?->toDateString(),
            ]);

            $agreement->recordedBy()->associate($recordedBy);
            $agreement->save();

            if ($signedCopy !== null) {
                $agreement->attachSignedCopy($signedCopy);
            }

            return $agreement;
        });
    }

    protected function defaultPercent(FeeAgreementKind $kind): float
    {
        return match ($kind) {
            FeeAgreementKind::Contingency => $this->settings->contingencyPercent,
            FeeAgreementKind::Estate => $this->settings->estatePercent,
            FeeAgreementKind::Mandate => 0.0,
        };
    }
}
