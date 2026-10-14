<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use App\Notifications\PasswordChanged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/*
 * What follows any change of password, however it was made: everyone else who
 * was signed in with the old one is signed out, the event is logged, and the
 * person is told by email. Telling them must never undo the change.
 */
class FinishPasswordChange
{
    /**
     * @param  string|null  $keepSessionId  The session the change was made from, which stays signed in
     */
    public function __invoke(User $user, string $event, string $description, ?string $keepSessionId = null): void
    {
        $user->forceFill(['remember_token' => Str::random(60)])->save();

        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->getKey())
                ->when($keepSessionId, fn ($query) => $query->where('id', '!=', $keepSessionId))
                ->delete();
        }

        $user->recordEvent($event, $description);

        try {
            $user->notifyNow(new PasswordChanged);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
