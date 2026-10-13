<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use App\Notifications\SignInCodeNotification;
use Illuminate\Support\Facades\Cache;

/*
 * The second step of signing in. A code is a six digit number emailed to the
 * person, kept here only as a keyed hash, good for a few minutes and for one
 * use. Wrong guesses are counted, and past the limit the code is void and a new
 * one has to be asked for.
 */
class SignInCode
{
    /** Whether this person is asked for a code: the firm has it on, and so have they. */
    public function requiredFor(User $user): bool
    {
        return (bool) config('security.sign_in_code.required') && $user->usesSignInCode();
    }

    /** Make a fresh code, replacing any waiting one, and email it. */
    public function issue(User $user): void
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $minutes = (int) config('security.sign_in_code.minutes');

        Cache::put($this->key($user), [
            'hash' => $this->hash($user, $code),
            'attempts' => 0,
            'sent_at' => now()->timestamp,
        ], now()->addMinutes($minutes));

        $user->notifyNow(new SignInCodeNotification($code, $minutes));
    }

    /** Whether a new code may be asked for yet, so the button cannot be used to flood a mailbox. */
    public function canResend(User $user): bool
    {
        $held = Cache::get($this->key($user));

        return $held === null
            || now()->timestamp - $held['sent_at'] >= (int) config('security.sign_in_code.resend_after_seconds');
    }

    /** Check a code. A right one is used up; a wrong one counts against the limit. */
    public function verify(User $user, string $given): bool
    {
        $held = Cache::get($this->key($user));

        if ($held === null) {
            return false;
        }

        if ($held['attempts'] >= (int) config('security.sign_in_code.attempts')) {
            Cache::forget($this->key($user));

            return false;
        }

        if (hash_equals($held['hash'], $this->hash($user, trim($given)))) {
            Cache::forget($this->key($user));

            return true;
        }

        $held['attempts']++;

        Cache::put($this->key($user), $held, now()->addMinutes((int) config('security.sign_in_code.minutes')));

        return false;
    }

    private function key(User $user): string
    {
        return 'sign-in-code:'.$user->getKey();
    }

    /** Keyed to the person and the app, so a leaked cache entry cannot be reversed to a code by guessing alone. */
    private function hash(User $user, string $code): string
    {
        return hash_hmac('sha256', $user->getKey().'|'.$code, (string) config('app.key'));
    }
}
