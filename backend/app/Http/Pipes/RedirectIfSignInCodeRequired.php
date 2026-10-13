<?php

declare(strict_types=1);

namespace App\Http\Pipes;

use App\Services\Auth\SignInCode;
use Illuminate\Contracts\Auth\StatefulGuard;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Contracts\RedirectsIfTwoFactorAuthenticatable;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\LoginRateLimiter;

/*
 * Between the password and being let in. The password is checked here exactly as
 * Fortify checks it; what changes is that a right password is not enough. The
 * person is remembered in the session as "past the password" only, a code is
 * emailed, and they are sent to enter it. Nobody is signed in until they do.
 */
class RedirectIfSignInCodeRequired extends RedirectIfTwoFactorAuthenticatable implements RedirectsIfTwoFactorAuthenticatable
{
    public function __construct(StatefulGuard $guard, LoginRateLimiter $limiter, private readonly SignInCode $codes)
    {
        parent::__construct($guard, $limiter);
    }

    public function handle($request, $next)
    {
        $user = $this->validateCredentials($request);

        if (! $this->codes->required()) {
            return $next($request);
        }

        $request->session()->put([
            'login.id' => $user->getKey(),
            'login.remember' => $request->boolean('remember'),
        ]);

        $this->codes->issue($user);

        return $request->wantsJson()
            ? response()->json(['sign_in_code' => true])
            : redirect()->route('sign-in-code');
    }
}
