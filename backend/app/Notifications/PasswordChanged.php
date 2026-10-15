<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/*
 * A security notice, so it is not one of the kinds a person can switch off:
 * the one time it matters is when it was not them.
 */
class PasswordChanged extends Notification
{
    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your password was changed')
            ->greeting('Hello '.$notifiable->name.',')
            ->line('The password for your staff account was changed on '.now()->format('d M Y \a\t H:i').'.')
            ->line('If this was you, there is nothing to do. If it was not, tell a director straight away: someone else may have access to your account.');
    }
}
