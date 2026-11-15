<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\StoreFeeAgreementRequest;
use App\Models\Matter\Matter;
use App\Services\Billing\FeeAgreementRefused;
use App\Services\Billing\RecordFeeAgreement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class FeeAgreementsController extends Controller
{
    public function store(StoreFeeAgreementRequest $request, Matter $matter, RecordFeeAgreement $recordAgreement): RedirectResponse
    {
        Gate::authorize('manageFees', $matter);

        try {
            $recordAgreement->record(
                matter: $matter,
                kind: $request->kind(),
                recordedBy: $request->user(),
                percent: $request->percent(),
                signedOn: $request->signedOn(),
                signedCopy: $request->file('signed_copy'),
            );
        } catch (FeeAgreementRefused $e) {
            return $this->back($matter)->withInput()->withErrors(['kind' => $e->getMessage()], 'fee_agreement');
        }

        return $this->back($matter)->with('status', 'Fee agreement recorded.');
    }

    private function back(Matter $matter): RedirectResponse
    {
        return redirect()->route('matter.show', [$matter, 'tab' => 'billing']);
    }
}
