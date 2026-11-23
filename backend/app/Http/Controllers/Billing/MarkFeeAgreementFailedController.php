<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Billing\FeeAgreement;
use App\Services\Billing\FeeAgreementRefused;
use App\Services\Billing\MarkFeeAgreementFailed;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class MarkFeeAgreementFailedController extends Controller
{
    public function __invoke(FeeAgreement $feeAgreement, MarkFeeAgreementFailed $markFailed): RedirectResponse
    {
        Gate::authorize('manageFees', $feeAgreement->matter);

        $back = redirect()->route('matter.show', [$feeAgreement->matter, 'tab' => 'billing']);

        try {
            $markFailed->fail($feeAgreement);
        } catch (FeeAgreementRefused $e) {
            return $back->withErrors(['fee_agreement' => $e->getMessage()], 'fee_agreement');
        }

        return $back->with('status', 'Claim marked failed. A mandate can now be recorded to bill the client by statement.');
    }
}
