<?php

declare(strict_types=1);

namespace App\Queries\Diary;

use App\Models\Diary\DiaryEntry;
use App\Models\User\User;

/*
 * What is on someone's own diary: how much is still to do, how much of it is
 * late, and what falls due today. It is always their own work, whatever else
 * they may see.
 */
class DiaryAgenda
{
    /** @return array<string, mixed> */
    public function for(User $user): array
    {
        return [
            'awaitingActionCount' => DiaryEntry::query()->assignedTo($user)->pending()->count(),
            'overdueCount' => DiaryEntry::query()->assignedTo($user)->overdue()->count(),
            'diaryToday' => DiaryEntry::query()
                ->assignedTo($user)
                ->pending()
                ->whereDate('due_at', today())
                ->with('matter')
                ->orderBy('due_at')
                ->get(),
        ];
    }
}
