<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\ReversePaymentRequest;
use App\Models\Billing\Payment;
use App\Services\Billing\PaymentRefused;
use App\Services\Billing\ReversePayment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class ReversePaymentController extends Controller
{
    public function __invoke(ReversePaymentRequest $request, Payment $payment, ReversePayment $reversePayment): RedirectResponse
    {
        Gate::authorize('reversePayments', $payment->bill->matter);

        $back = redirect()->route('bill.show', $payment->bill);

        try {
            $reversePayment->reverse($payment, $request->user(), $request->validated('reason'));
        } catch (PaymentRefused $e) {
            return $back->withErrors(['reason' => $e->getMessage()], ReversePaymentRequest::bagFor($payment->getKey()));
        }

        return $back->with('status', 'Payment reversed. It no longer counts towards the bill.');
    }
}
