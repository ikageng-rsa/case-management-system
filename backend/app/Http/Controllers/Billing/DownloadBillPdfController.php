<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Billing\Bill;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/*
 * A bill as a PDF, laid out as date, item, description, fees and
 * disbursements with the totals under them. A draft is stamped DRAFT and a
 * voided bill VOID, so neither can be mistaken for one that is owed.
 */
class DownloadBillPdfController extends Controller
{
    public function __invoke(Request $request, Bill $bill): Response
    {
        Gate::authorize('viewBills', $bill->matter);

        $bill->load(['matter', 'lines']);
        $bill->recordAccess('downloaded', ['ip' => $request->ip()]);

        $name = $bill->number ? str_replace('/', '-', $bill->number) : 'draft-'.substr($bill->getKey(), 0, 8);

        return Pdf::loadView('pdf.bill', ['bill' => $bill])->setPaper('a4')->download("{$name}.pdf");
    }
}
