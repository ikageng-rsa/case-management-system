<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\SignInRequest;
use App\Http\Resources\Auth\SignInChallengeResource;
use App\Http\Resources\User\UserResource;
use App\Models\User\User;
use App\Services\Auth\SignInChallenge;
use App\Services\Auth\SignInCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class SignInController extends Controller
{
    /**
     * The password, which on a phone is always followed by an emailed code. Only
     * where the firm has codes off, as on a developer's machine, is a token given
     * at once.
     */
    public function store(SignInRequest $request, SignInCode $codes, SignInChallenge $challenges): JsonResponse
    {
        $user = User::query()->where('email', $request->email())->first();

        if ($user === null || ! Hash::check((string) $request->validated('password'), $user->password)) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        if (! config('security.sign_in_code.required')) {
            return (new UserResource($user))
                ->additional(['meta' => ['token' => $user->createToken($request->validated('device_name'))->plainTextToken]])
                ->response()
                ->setStatusCode(201);
        }

        $codes->issue($user);

        return (new SignInChallengeResource([
            'id' => $challenges->open($user, $request->validated('device_name')),
            'expires_in' => (int) config('security.sign_in_code.minutes') * 60,
        ]))->response();
    }
}
