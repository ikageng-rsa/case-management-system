<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\Notification\NotificationType;
use App\Models\DiaryEntry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;

#[DeleteWhenMissingModels]
class DiaryEntryOverdue extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly DiaryEntry $entry,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        // Always kept in the system; emailed too when the person has asked for that.
        return $notifiable->wantsEmailFor(NotificationType::DiaryOverdue)
            ? ['database', 'mail']
            : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $reference = $this->entry->matter?->reference;

        return (new MailMessage)
            ->subject('Overdue: a diary entry'.($reference ? " on {$reference}" : ''))
            ->greeting('Hello '.$notifiable->name.',')
            ->line('A diary entry assigned to you is past its due date and not yet done:')
            ->line('**'.$this->entry->body.'**')
            ->line('It was due '.$this->entry->due_at->format('d M Y H:i').'.')
            ->action('Open the matter', route('matter.show', [$this->entry->matter, 'tab' => 'diary']))
            ->line('You can stop these emails from your account page.');
    }

    /**
     * The entry is reloaded when the queued notification runs, so this skips
     * entries completed or rescheduled since it was queued.
     */
    public function shouldSend(object $notifiable, string $channel): bool
    {
        return $this->entry->isOverdue();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'diary_entry_id' => $this->entry->getKey(),
            'matter_id' => $this->entry->matter_id,
            'matter_reference' => $this->entry->matter?->reference,
            'due_at' => $this->entry->due_at->toIso8601String(),
        ];
    }
}
