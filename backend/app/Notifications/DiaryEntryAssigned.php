<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\Notification\NotificationType;
use App\Models\DiaryEntry;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;

class DiaryEntryAssigned extends PersonalNotification
{
    public function __construct(
        public readonly DiaryEntry $entry,
        public readonly ?User $by,
    ) {}

    public function type(): NotificationType
    {
        return NotificationType::DiaryAssigned;
    }

    private function message(): string
    {
        return sprintf(
            '%s gave you a diary entry on %s, due %s: %s',
            $this->by?->name ?? 'Someone',
            $this->entry->matter->reference,
            $this->entry->due_at->format('d M Y H:i'),
            $this->entry->body,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->type()->value,
            'message' => $this->message(),
            'url' => route('matter.show', [$this->entry->matter, 'tab' => 'diary']),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('A diary entry for you on '.$this->entry->matter->reference)
            ->greeting('Hello '.$notifiable->name.',')
            ->line($this->message())
            ->action('Open the diary', route('matter.show', [$this->entry->matter, 'tab' => 'diary']))
            ->line('You can change what you are emailed about from your account page.');
    }
}
