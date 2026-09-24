<?php

declare(strict_types=1);

namespace App\Services\Diary;

use App\Models\DiaryEntry;
use App\Models\Narration;
use Carbon\CarbonInterface;

/*
 * Replaces an entry's editable details. A null narration removes the link,
 * so a narration linked by mistake can be undone.
 */
class UpdateDiaryEntry
{
    public function update(
        DiaryEntry $entry,
        string $body,
        CarbonInterface $dueAt,
        ?Narration $narration,
    ): DiaryEntry {
        $entry->fill([
            'body' => $body,
            'due_at' => $dueAt,
        ]);

        $entry->linkNarration($narration);
        $entry->save();

        return $entry;
    }
}
