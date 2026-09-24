<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DiaryEntry;
use App\Notifications\DiaryEntryOverdue;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('diary:alert-overdue')]
#[Description('Notify assignees of diary entries that are past due and not yet complete')]
class SendOverdueDiaryAlerts extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $overdue = DiaryEntry::overdue()->with(['assignee', 'matter'])->get();

        foreach ($overdue as $entry) {
            $entry->assignee?->notify(new DiaryEntryOverdue($entry));
        }

        $this->info("Sent {$overdue->count()} overdue diary alert(s).");

        return self::SUCCESS;
    }
}
