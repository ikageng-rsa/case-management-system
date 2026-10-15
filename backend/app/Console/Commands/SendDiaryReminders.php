<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DiaryEntry;
use App\Notifications\DiaryEntryDueSoon;
use App\Services\Notifications\NotifyUser;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('diary:remind-upcoming')]
#[Description('Remind assignees, once each, of diary entries that are coming due, as early as each entry asks')]
class SendDiaryReminders extends Command
{
    /**
     * Each entry asks how long before its time it should be reminded, and is
     * reminded once, when that moment has come. The mark is only set once the
     * person was actually told, so a failure is tried again on the next run.
     * Entries on closed matters, and entries already done, are left alone.
     */
    public function handle(NotifyUser $notify): int
    {
        $told = 0;

        DiaryEntry::awaitingReminder()
            ->whereHas('matter', fn ($matter) => $matter->open())
            ->with(['assignee', 'matter'])
            ->each(function (DiaryEntry $entry) use ($notify, &$told): void {
                if ($entry->assignee === null || $entry->remindAt()->isFuture()) {
                    return;
                }

                if ($notify->about($entry->assignee, new DiaryEntryDueSoon($entry))) {
                    $entry->forceFill(['reminder_sent_at' => now()])->save();
                    $told++;
                }
            });

        $this->info("Reminded people of {$told} diary entr".($told === 1 ? 'y' : 'ies').'.');

        return self::SUCCESS;
    }
}
