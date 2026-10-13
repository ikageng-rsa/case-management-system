<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\UserInvitation;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserInvitationNotification extends Notification
{
    public function __construct(
        private readonly UserInvitation $invitation,
        private readonly string $token,
    ) {}

    /** Where the link in the email goes. */
    public function url(): string
    {
        return route('invitation.show', $this->token);
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $days = $this->invitation->lifetime_days;

        return (new MailMessage)
            ->subject('You have been invited to '.config('app.name'))
            ->greeting('Hello '.$this->invitation->name.',')
            ->line(($this->invitation->inviter?->name ?? 'Someone at the firm').' has invited you to join.')
            ->action('Accept the invitation', $this->url())
            ->line("This link works once and expires in {$days} ".($days === 1 ? 'day' : 'days').'.')
            ->line('If you were not expecting this, you can ignore this email and nothing will happen.');
    }
}
