<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\StoreDisbursementRequest;
use App\Models\Billing\Disbursement;
use App\Models\Matter\Matter;
use App\Services\Billing\RecordDisbursement;
use App\Services\Billing\RemoveDisbursement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class DisbursementsController extends Controller
{
    public function store(StoreDisbursementRequest $request, Matter $matter, RecordDisbursement $recordDisbursement): RedirectResponse
    {
        Gate::authorize('manageFees', $matter);

        $recordDisbursement->record($matter, $request->disbursement(), $request->user(), $request->file('receipt'));

        return $this->back($matter)->with('status', 'Disbursement recorded.');
    }

    public function destroy(Disbursement $disbursement, RemoveDisbursement $removeDisbursement): RedirectResponse
    {
        Gate::authorize('manageFees', $disbursement->matter);

        $removeDisbursement->remove($disbursement);

        return $this->back($disbursement->matter)->with('status', 'Disbursement removed.');
    }

    private function back(Matter $matter): RedirectResponse
    {
        return redirect()->route('matter.show', [$matter, 'tab' => 'billing']);
    }
}
