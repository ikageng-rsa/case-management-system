<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\DiaryEntry;

class DiaryEntryObserver
{
    /**
     * A date that moves is a new deadline, so being overdue against it is news
     * again. the mark that the person was told is cleared. Setting the mark in the
     * same save is respected, so the alert command can record that it has told them.
     */
    public function saving(DiaryEntry $entry): void
    {
        if ($entry->exists && $entry->isDirty('due_at') && ! $entry->isDirty('overdue_notified_at')) {
            $entry->overdue_notified_at = null;
        }

        $this->settleReminder($entry);
    }

    /*
     * A reminder is for something still ahead. Whenever the date or the lead time
     * is set or moved the reminder starts afresh, unless its moment has already
     * gone: then it is treated as sent, so a new entry due in an hour does not
     * announce, at once, that it is due within a day.
     */
    private function settleReminder(DiaryEntry $entry): void
    {
        $changed = ! $entry->exists || $entry->isDirty('due_at') || $entry->isDirty('remind_before_minutes');

        if (! $changed || $entry->isDirty('reminder_sent_at')) {
            return;
        }

        $remindAt = $entry->remindAt();

        $entry->reminder_sent_at = $remindAt !== null && $remindAt->isPast() ? now() : null;
    }
}
