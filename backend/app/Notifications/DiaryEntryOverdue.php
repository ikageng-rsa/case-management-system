<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\DiaryEntry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
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
        return ['database'];
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
