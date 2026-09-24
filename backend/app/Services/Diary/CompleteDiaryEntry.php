<?php

declare(strict_types=1);

namespace App\Services\Diary;

use App\Models\DiaryEntry;
use App\Models\Narration;
use App\Models\User;

/*
 * Marks a diarised action done, recording who completed it and, optionally,
 * the narration for the work that completed it
 */
class CompleteDiaryEntry
{
    public function complete(DiaryEntry $entry, User $user, ?Narration $narration = null): DiaryEntry
    {
        if ($entry->isComplete()) {
            return $entry;
        }

        if ($narration !== null) {
            $entry->linkNarration($narration);
        }

        $entry->markComplete($user);

        return $entry;
    }
}
