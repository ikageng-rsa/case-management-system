<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\SignInCodeRequest;
use App\Http\Resources\User\UserResource;
use App\Services\Auth\SignInChallenge;
use App\Services\Auth\SignInCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class VerifySignInCodeController extends Controller
{
    /** The emailed code, which gives the device its token. */
    public function __invoke(SignInCodeRequest $request, SignInCode $codes, SignInChallenge $challenges): JsonResponse
    {
        $challenge = (string) $request->validated('challenge_id');
        $user = $challenges->user($challenge);

        if ($user === null) {
            throw ValidationException::withMessages(['challenge_id' => 'Signing in has run out. Start again with your password.']);
        }

        if (! $codes->verify($user, (string) $request->validated('code'))) {
            throw ValidationException::withMessages(['code' => 'That code is wrong or has run out. Check it, or ask for a new one.']);
        }

        $deviceName = $challenges->close($challenge);

        return (new UserResource($user))->asSelf()
            ->additional(['meta' => ['token' => $user->createToken($deviceName)->plainTextToken]])
            ->response()
            ->setStatusCode(201);
    }
}
