<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\User;
use App\Notifications\PersonalNotification;
use Throwable;

class NotifyUser
{
    /**
     * Tell someone, now. A notification is never worth failing what caused it:
     * if mail cannot be sent the matter is still assigned, and the problem is
     * reported for someone to see.
     */
    public function about(User $user, PersonalNotification $notification): void
    {
        try {
            $user->notifyNow($notification);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
