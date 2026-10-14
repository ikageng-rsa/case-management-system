<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;

/*
 * Fortify tells the person when no one has the address they typed, which lets
 * anyone discover who works at the firm. This answers as it would for a real
 * address
 */
class SameAnswerWhateverTheAddress implements FailedPasswordResetLinkRequestResponse, SuccessfulPasswordResetLinkRequestResponse
{
    public function toResponse($request): RedirectResponse|JsonResponse
    {
        $message = 'If that address is on a staff account, a link to choose a new password is on its way.';

        return $request->wantsJson()
            ? new JsonResponse(['message' => $message], 200)
            : back()->with('status', $message);
    }
}
