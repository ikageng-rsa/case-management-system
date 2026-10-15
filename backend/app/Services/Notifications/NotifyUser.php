<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\User;
use App\Notifications\PersonalNotification;
use Throwable;

class NotifyUser
{
    /**
     * Tell someone, now. Report any failing notification
     */
    public function about(User $user, PersonalNotification $notification): bool
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
