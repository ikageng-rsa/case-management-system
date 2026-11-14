<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\StoreBillRequest;
use App\Models\Billing\Bill;
use App\Models\Matter\Matter;
use App\Queries\Billing\UnbilledWork;
use App\Services\Billing\BillRefused;
use App\Services\Billing\DeleteDraftBill;
use App\Services\Billing\DraftBill;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class BillsController extends Controller
{
    public function store(StoreBillRequest $request, Matter $matter, DraftBill $draftBill): RedirectResponse
    {
        Gate::authorize('draftBills', $matter);

        try {
            $bill = $draftBill->draft(
                matter: $matter,
                kind: $request->kind(),
                addressee: $request->validated('addressee'),
                draftedBy: $request->user(),
                scale: $request->scale(),
                from: $request->from(),
                to: $request->to(),
                timeRates: $request->timeRates(),
            );
        } catch (BillRefused $e) {
            return redirect()->route('matter.show', [$matter, 'tab' => 'billing'])
                ->withInput()->withErrors(['bill' => $e->problems], 'bill');
        }

        return redirect()->route('bill.show', $bill)->with('status', 'Draft drawn. Check it before it is issued.');
    }

    public function show(Bill $bill, UnbilledWork $unbilled): View
    {
        Gate::authorize('viewBills', $bill->matter);

        $bill->load(['matter.client', 'lines.user', 'lines.overriddenBy', 'draftedBy', 'issuedBy', 'voidedBy']);

        return view('bill', [
            'bill' => $bill,
            // What a draft can still take: the matter's work and costs not yet billed on a bill of its kind.
            'unbilled' => $bill->isDraft() ? $unbilled->for($bill->matter, $bill->kind) : null,
        ]);
    }

    public function destroy(Bill $bill, DeleteDraftBill $deleteDraft): RedirectResponse
    {
        Gate::authorize('draftBills', $bill->matter);

        try {
            $deleteDraft->delete($bill);
        } catch (BillRefused $e) {
            return redirect()->route('bill.show', $bill)->withErrors(['bill' => $e->problems], 'bill');
        }

        return redirect()->route('matter.show', [$bill->matter, 'tab' => 'billing'])->with('status', 'Draft deleted. Its work is free to bill again.');
    }
}
