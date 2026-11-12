<?php

declare(strict_types=1);

namespace App\Services\Diary;

use App\Models\Diary\DiaryEntry;
use App\Models\Narration\Narration;
use App\Models\User\User;
use App\Notifications\DiaryEntryAssigned;
use App\Services\Notifications\NotifyUser;
use Carbon\CarbonInterface;

class UpdateDiaryEntry
{
    public function __construct(private readonly NotifyUser $notify) {}

    public function update(
        DiaryEntry $entry,
        string $body,
        CarbonInterface $dueAt,
        ?Narration $narration,
        ?User $assignee = null,
        ?User $editedBy = null,
    ): DiaryEntry {
        $entry->fill([
            'body' => $body,
            'due_at' => $dueAt,
        ]);

        if ($entry->isDirty('due_at')) {
            $entry->forceFill(['reminder_sent_at' => null, 'overdue_notified_at' => null]);
        }

        $handedOver = $assignee !== null && $entry->assigned_to !== $assignee->getKey();

        if ($handedOver) {
            $entry->assignee()->associate($assignee);
        }

        $entry->linkNarration($narration);
        $entry->save();

        if ($handedOver && ! $assignee->is($editedBy)) {
            $this->notify->about($assignee, new DiaryEntryAssigned($entry, $editedBy));
        }

        return $entry;
    }
}
