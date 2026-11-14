<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Billing\Bill;
use App\Services\Billing\BillRefused;
use App\Services\Billing\IssueBill;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class IssueBillController extends Controller
{
    public function __invoke(Request $request, Bill $bill, IssueBill $issueBill): RedirectResponse
    {
        Gate::authorize('issueBills', $bill->matter);

        try {
            $issueBill->issue($bill, $request->user());
        } catch (BillRefused $e) {
            return redirect()->route('bill.show', $bill)->withErrors(['bill' => $e->problems], 'bill');
        }

        return redirect()->route('bill.show', $bill)->with('status', "Bill issued as {$bill->number}.");
    }
}
