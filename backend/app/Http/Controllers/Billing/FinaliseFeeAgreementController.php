<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Http\BillingHome;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\FinaliseFeeAgreementRequest;
use App\Models\Billing\FeeAgreement;
use App\Services\Billing\FeeAgreementRefused;
use App\Services\Billing\FinaliseFeeAgreement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class FinaliseFeeAgreementController extends Controller
{
    public function __invoke(FinaliseFeeAgreementRequest $request, FeeAgreement $feeAgreement, FinaliseFeeAgreement $finalise): RedirectResponse
    {
        Gate::authorize('manageFees', $feeAgreement->matter);

        $back = redirect()->to(BillingHome::url($feeAgreement->matter));

        try {
            $finalise->finalise($feeAgreement, $request->amountReceivedCents());
        } catch (FeeAgreementRefused $e) {
            return $back->withErrors(['amount_received' => $e->getMessage()], 'fee_agreement_finalise');
        }

        return $back->with('status', 'Fee agreement finalised.');
    }
}
