<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/*
 * Signing in to the API has no session to hold who has passed the password, so
 * the app is handed a challenge instead: a random id that stands for the person
 * and the device until the emailed code is entered. Only its hash is kept, it
 * lasts as long as the code does, and it works once.
 */
class SignInChallenge
{
    /** Open a challenge for someone who has given the right password on a device. */
    public function open(User $user, string $deviceName): string
    {
        $challenge = Str::random(40);

        Cache::put($this->key($challenge), [
            'user_id' => $user->getKey(),
            'device_name' => $deviceName,
        ], now()->addMinutes((int) config('security.sign_in_code.minutes')));

        return $challenge;
    }

    /** Who the challenge stands for, while it is still open. */
    public function user(string $challenge): ?User
    {
        $held = Cache::get($this->key($challenge));

        return $held === null ? null : User::query()->find($held['user_id']);
    }

    /** Keep the challenge open as long as a newly sent code. */
    public function renew(string $challenge): void
    {
        $held = Cache::get($this->key($challenge));

        if ($held === null) {
            return;
        }

        Cache::put($this->key($challenge), $held, now()->addMinutes((int) config('security.sign_in_code.minutes')));
    }

    /** Close the challenge, and give back the device it was opened on. */
    public function close(string $challenge): ?string
    {
        return Cache::pull($this->key($challenge))['device_name'] ?? null;
    }

    private function key(string $challenge): string
    {
        return 'api-sign-in:'.hash('sha256', $challenge);
    }
}
