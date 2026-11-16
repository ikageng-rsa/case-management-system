<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\StorePaymentRequest;
use App\Models\Billing\Bill;
use App\Services\Billing\PaymentRefused;
use App\Services\Billing\RecordPayment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class PaymentsController extends Controller
{
    public function store(StorePaymentRequest $request, Bill $bill, RecordPayment $recordPayment): RedirectResponse
    {
        Gate::authorize('recordPayments', $bill->matter);

        $back = redirect()->route('bill.show', $bill);

        try {
            $payment = $recordPayment->record($bill, $request->payment(), $request->user(), $request->proof());
        } catch (PaymentRefused $e) {
            return $back->withInput()->withErrors(['amount' => $e->getMessage()], 'payment');
        }

        return $back->with('status', $payment->bill->balanceCents() === 0
            ? 'Payment recorded. The bill is settled.'
            : 'Payment recorded.');
    }
}
