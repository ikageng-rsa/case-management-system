<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SignInCodeNotification extends Notification
{
    public function __construct(
        private readonly string $code,
        private readonly int $minutes,
    ) {}

    /** The code itself, for the email and for tests that follow it. */
    public function code(): string
    {
        return $this->code;
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your sign-in code: '.$this->code)
            ->greeting('Hello '.$notifiable->name.',')
            ->line('Use this code to finish signing in to '.config('app.name').':')
            ->line('**'.$this->code.'**')
            ->line("It works once and expires in {$this->minutes} minutes.")
            ->line('If this was not you, someone knows your password. Change it, and tell a director.');
    }
}
