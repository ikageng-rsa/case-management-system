<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\Notification\NotificationType;
use App\Models\DiaryEntry;
use Illuminate\Notifications\Messages\MailMessage;

class DiaryEntryDueSoon extends PersonalNotification
{
    public function __construct(public readonly DiaryEntry $entry) {}

    public function type(): NotificationType
    {
        return NotificationType::DiaryDueSoon;
    }

    /** Not sent if it was done, or its date moved, between being chosen and being sent. */
    public function shouldSend(object $notifiable, string $channel): bool
    {
        return $this->entry->isPending() && ! $this->entry->isDue();
    }

    private function message(): string
    {
        return sprintf(
            'A diary entry on %s is due %s: %s',
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
            'diary_entry_id' => $this->entry->getKey(),
            'due_at' => $this->entry->due_at->toIso8601String(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Coming due: a diary entry on '.$this->entry->matter->reference)
            ->greeting('Hello '.$notifiable->name.',')
            ->line('A diary entry assigned to you falls due on '.$this->entry->due_at->format('d M Y \a\t H:i').':')
            ->line('**'.$this->entry->body.'**')
            ->action('Open the diary', route('matter.show', [$this->entry->matter, 'tab' => 'diary']))
            ->line('You can change how early you are reminded, and where, from your account page.');
    }
}
