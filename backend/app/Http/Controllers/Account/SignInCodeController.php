<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\User\User;
use App\Services\Auth\SignInCode;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/*
 * The page after the password. Nothing here signs anyone in until the code they
 * were emailed is entered; arriving without having passed the password sends them
 * back to the start.
 */
class SignInCodeController extends Controller
{
    public function __construct(private readonly SignInCode $codes) {}

    public function show(Request $request): View|RedirectResponse
    {
        $user = $this->pending($request);

        if ($user === null) {
            return redirect()->route('login');
        }

        return view('auth.sign-in-code', ['email' => $user->email]);
    }

    public function verify(Request $request): RedirectResponse
    {
        $user = $this->pending($request);

        if ($user === null) {
            return redirect()->route('login');
        }

        $request->validate(['code' => ['required', 'string']]);

        if (! $this->codes->verify($user, (string) $request->input('code'))) {
            throw ValidationException::withMessages([
                'code' => 'That code is wrong or has run out. Check it, or ask for a new one.',
            ]);
        }

        $remember = (bool) $request->session()->pull('login.remember', false);
        $request->session()->forget('login.id');

        Auth::guard(config('fortify.guard'))->login($user, $remember);
        $request->session()->regenerate();

        return redirect()->intended(config('fortify.home'));
    }

    public function resend(Request $request): RedirectResponse
    {
        $user = $this->pending($request);

        if ($user === null) {
            return redirect()->route('login');
        }

        if (! $this->codes->canResend($user)) {
            return back()->withErrors(['code' => 'A code was just sent. Give it a minute before asking again.']);
        }

        $this->codes->issue($user);

        return back()->with('status', 'A new code is on its way.');
    }

    /** Who has passed the password and is waiting to enter a code. */
    private function pending(Request $request): ?User
    {
        $id = $request->session()->get('login.id');

        return $id === null ? null : User::query()->find($id);
    }
}
