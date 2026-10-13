<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\Notification\NotificationType;
use App\Services\Notifications\ResolveNotificationChannels;
use Illuminate\Notifications\Notification;

/*
 * Something that has to do with one person. It goes only where they have asked
 * for it to go: in the system, by email, both, or nowhere at all.
 */
abstract class PersonalNotification extends Notification
{
    abstract public function type(): NotificationType;

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return app(ResolveNotificationChannels::class)->for($notifiable, $this->type());
    }
}
