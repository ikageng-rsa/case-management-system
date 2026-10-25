<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\User\User;
use App\Notifications\PersonalNotification;
use Illuminate\Support\Facades\DB;
use Throwable;

/*
 * Tells someone about a change. Inside a transaction the message waits until
 * the change is committed, so an email never announces something that was
 * then rolled back. Outside one it goes at once.
 */
class NotifyUser
{
    /**
     * Whether it was sent. Inside a transaction it is not sent yet, so this is
     * true, and a failure is only reported once the transaction commits.
     */
    public function about(User $user, PersonalNotification $notification): bool
    {
        $sent = null;

        DB::afterCommit(function () use ($user, $notification, &$sent): void {
            $sent = $this->send($user, $notification);
        });

        return $sent ?? true;
    }

    protected function send(User $user, PersonalNotification $notification): bool
    {
        try {
            $user->notifyNow($notification);

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }
}
