<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Billing\Disbursement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/* The receipt for a disbursement, recorded in the audit log as read. */
class DownloadDisbursementReceiptController extends Controller
{
    public function __invoke(Request $request, Disbursement $disbursement): Response
    {
        Gate::authorize('manageFees', $disbursement->matter);

        $receipt = $disbursement->receipt();

        abort_if($receipt === null, 404);

        $disbursement->recordAccess('downloaded', ['ip' => $request->ip()]);

        return $receipt->toResponse($request);
    }
}
