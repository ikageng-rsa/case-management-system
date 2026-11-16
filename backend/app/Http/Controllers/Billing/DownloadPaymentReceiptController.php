<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Billing\Payment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/* The receipt for a payment, as a PDF. A reversed payment has none. */
class DownloadPaymentReceiptController extends Controller
{
    public function __invoke(Request $request, Payment $payment): Response
    {
        Gate::authorize('viewBills', $payment->bill->matter);

        abort_if($payment->isReversed(), 404);

        $payment->load('bill.matter');
        $payment->recordAccess('downloaded', ['ip' => $request->ip(), 'part' => 'receipt']);

        return Pdf::loadView('pdf.receipt', ['payment' => $payment])->setPaper('a4')->download("receipt-{$payment->receiptNumber()}.pdf");
    }
}
