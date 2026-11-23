<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Billing\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/* The proof of a payment, recorded in the audit log as read. */
class DownloadPaymentProofController extends Controller
{
    public function __invoke(Request $request, Payment $payment): Response
    {
        Gate::authorize('viewBills', $payment->bill->matter);

        $proof = $payment->proof();

        abort_if($proof === null, 404);

        $payment->recordAccess('downloaded', ['ip' => $request->ip()]);

        return $proof->toResponse($request);
    }
}
