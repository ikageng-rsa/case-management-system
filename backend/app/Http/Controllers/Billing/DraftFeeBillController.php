<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\DraftFeeBillRequest;
use App\Models\Billing\FeeAgreement;
use App\Services\Billing\BillRefused;
use App\Services\Billing\DraftFeeBill;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class DraftFeeBillController extends Controller
{
    public function __invoke(DraftFeeBillRequest $request, FeeAgreement $feeAgreement, DraftFeeBill $draftFeeBill): RedirectResponse
    {
        Gate::authorize('draftBills', $feeAgreement->matter);

        try {
            $bill = $draftFeeBill->draft(
                agreement: $feeAgreement,
                addressee: $request->validated('addressee'),
                draftedBy: $request->user(),
                address: $request->validated('address'),
            );
        } catch (BillRefused $e) {
            return redirect()->route('matter.show', [$feeAgreement->matter, 'tab' => 'billing'])
                ->withInput()->withErrors(['fee_bill' => $e->problems], DraftFeeBillRequest::bagFor($feeAgreement->getKey()));
        }

        return redirect()->route('bill.show', $bill)->with('status', 'Draft drawn. Check it before it is issued.');
    }
}
