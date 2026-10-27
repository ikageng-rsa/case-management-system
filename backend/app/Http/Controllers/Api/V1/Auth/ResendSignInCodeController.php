<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\ResendSignInCodeRequest;
use App\Services\Auth\SignInChallenge;
use App\Services\Auth\SignInCode;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class ResendSignInCodeController extends Controller
{
    public function __invoke(ResendSignInCodeRequest $request, SignInCode $codes, SignInChallenge $challenges): Response
    {
        $challenge = (string) $request->validated('challenge_id');
        $user = $challenges->user($challenge);

        if ($user === null) {
            throw ValidationException::withMessages(['challenge_id' => 'Signing in has run out. Start again with your password.']);
        }

        if (! $codes->canResend($user)) {
            throw ValidationException::withMessages(['code' => 'A code was just sent. Give it a minute before asking again.']);
        }

        $codes->issue($user);
        $challenges->renew($challenge);

        return response()->noContent();
    }
}
