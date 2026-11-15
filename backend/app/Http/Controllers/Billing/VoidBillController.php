<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\VoidBillRequest;
use App\Models\Billing\Bill;
use App\Services\Billing\BillRefused;
use App\Services\Billing\VoidBill;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class VoidBillController extends Controller
{
    public function __invoke(VoidBillRequest $request, Bill $bill, VoidBill $voidBill): RedirectResponse
    {
        Gate::authorize('voidBills', $bill->matter);

        try {
            $voidBill->void($bill, $request->user(), $request->validated('reason'));
        } catch (BillRefused $e) {
            return redirect()->route('bill.show', $bill)->withErrors(['bill' => $e->problems], 'bill');
        }

        return redirect()->route('bill.show', $bill)->with('status', "Bill {$bill->number} voided. Its work is free to bill again.");
    }
}
