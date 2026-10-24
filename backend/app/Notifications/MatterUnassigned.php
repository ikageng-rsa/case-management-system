<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\Matter\Assignment;
use App\Enums\Notification\NotificationType;
use App\Models\Matter\Matter;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;

class MatterUnassigned extends PersonalNotification
{
    public function __construct(
        public readonly Matter $matter,
        public readonly Assignment $capacity,
        public readonly ?User $by,
    ) {}

    public function type(): NotificationType
    {
        return NotificationType::MatterUnassigned;
    }

    private function message(): string
    {
        return sprintf(
            '%s took you off matter %s, where you were %s.',
            $this->by?->name ?? 'Someone',
            $this->matter->reference,
            $this->capacity->value,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        // Not a link to the file: they may no longer be able to open it.
        return [
            'type' => $this->type()->value,
            'message' => $this->message(),
            'url' => null,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('You have been taken off matter '.$this->matter->reference)
            ->greeting('Hello '.$notifiable->name.',')
            ->line($this->message())
            ->line('You can change what you are emailed about from your account page.');
    }
}
