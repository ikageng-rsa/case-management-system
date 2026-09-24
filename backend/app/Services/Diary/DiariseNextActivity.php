<?php

declare(strict_types=1);

namespace App\Services\Diary;

use App\Models\DiaryEntry;
use App\Models\Matter;
use App\Models\User;
use Carbon\CarbonInterface;

class DiariseNextAction
{
    public function diarise(
        Matter $matter,
        User $assignee,
        string $body,
        CarbonInterface $dueAt,
    ): DiaryEntry {
        $entry = new DiaryEntry([
            'body' => $body,
            'due_at' => $dueAt,
        ]);

        $entry->matter()->associate($matter);
        $entry->assignee()->associate($assignee);
        $entry->save();

        return $entry;
    }
}