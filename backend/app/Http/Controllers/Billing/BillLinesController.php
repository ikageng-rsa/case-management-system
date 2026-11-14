<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\ChangeBillLineAmountRequest;
use App\Models\Billing\Bill;
use App\Models\Billing\BillLine;
use App\Services\Billing\BillRefused;
use App\Services\Billing\ChangeBillLineAmount;
use App\Services\Billing\RemoveBillLine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BillLinesController extends Controller
{
    public function update(ChangeBillLineAmountRequest $request, Bill $bill, BillLine $line, ChangeBillLineAmount $changeAmount): RedirectResponse
    {
        Gate::authorize('draftBills', $bill->matter);

        try {
            $changeAmount->change($line, $request->amountCents(), $request->user());
        } catch (BillRefused $e) {
            return $this->back($bill)->withErrors(['bill' => $e->problems], 'bill');
        }

        return $this->back($bill)->with('status', 'Line changed.');
    }

    public function destroy(Request $request, Bill $bill, BillLine $line, RemoveBillLine $removeLine): RedirectResponse
    {
        Gate::authorize('draftBills', $bill->matter);

        try {
            $removeLine->remove($line, $request->user());
        } catch (BillRefused $e) {
            return $this->back($bill)->withErrors(['bill' => $e->problems], 'bill');
        }

        return $this->back($bill)->with('status', 'Line removed. Its work is free to bill again.');
    }

    private function back(Bill $bill): RedirectResponse
    {
        return redirect()->route('bill.show', $bill);
    }
}
