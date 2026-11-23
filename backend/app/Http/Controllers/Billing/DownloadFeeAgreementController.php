<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Billing\FeeAgreement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/* The signed copy of a fee agreement, recorded in the audit log as read. */
class DownloadFeeAgreementController extends Controller
{
    public function __invoke(Request $request, FeeAgreement $feeAgreement): Response
    {
        Gate::authorize('manageFees', $feeAgreement->matter);

        $signedCopy = $feeAgreement->signedCopy();

        abort_if($signedCopy === null, 404);

        $feeAgreement->recordAccess('downloaded', ['ip' => $request->ip()]);

        return $signedCopy->toResponse($request);
    }
}
