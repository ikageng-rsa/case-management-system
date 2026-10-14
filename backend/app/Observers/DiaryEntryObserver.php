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
    }
}
