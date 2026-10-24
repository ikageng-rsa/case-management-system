<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\Matter\Assignment;
use App\Enums\Notification\NotificationType;
use App\Models\Matter\Matter;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;

class MatterAssigned extends PersonalNotification
{
    public function __construct(
        public readonly Matter $matter,
        public readonly Assignment $capacity,
        public readonly ?User $by,
    ) {}

    public function type(): NotificationType
    {
        return NotificationType::MatterAssigned;
    }

    private function message(): string
    {
        return sprintf(
            '%s put you on matter %s as %s.',
            $this->by?->name ?? 'Someone',
            $this->matter->reference,
            $this->capacity->value,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->type()->value,
            'message' => $this->message(),
            'url' => route('matter.show', $this->matter),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('You have been put on matter '.$this->matter->reference)
            ->greeting('Hello '.$notifiable->name.',')
            ->line($this->message())
            ->action('Open the matter', route('matter.show', $this->matter))
            ->line('You can change what you are emailed about from your account page.');
    }
}
