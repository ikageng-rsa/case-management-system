<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DiaryEntry;
use App\Notifications\DiaryEntryOverdue;
use App\Services\Notifications\NotifyUser;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('diary:alert-overdue')]
#[Description('Tell assignees, once each, of diary entries that are past due and not yet done')]
class SendOverdueDiaryAlerts extends Command
{
    /**
     * Each entry is reported once: when it has been, it is marked
     */
    public function handle(NotifyUser $notify): int
    {
        $told = 0;

        DiaryEntry::awaitingOverdueNotice()
            ->whereHas('matter', fn ($matter) => $matter->open())
            ->with(['assignee', 'matter'])
            ->each(function (DiaryEntry $entry) use ($notify, &$told): void {
                if ($entry->assignee === null) {
                    return;
                }

                // Marked only once it has gone: a failure is tried again next run.
                if ($notify->about($entry->assignee, new DiaryEntryOverdue($entry))) {
                    $entry->forceFill(['overdue_notified_at' => now()])->save();
                    $told++;
                }
            });

        $this->info("Told people of {$told} overdue diary entr".($told === 1 ? 'y' : 'ies').'.');

        return self::SUCCESS;
    }
}
