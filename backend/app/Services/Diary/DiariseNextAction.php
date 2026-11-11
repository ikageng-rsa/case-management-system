<?php

declare(strict_types=1);

namespace App\Services\Diary;

use App\Models\Diary\DiaryEntry;
use App\Models\Matter\Matter;
use App\Models\User\User;
use App\Notifications\DiaryEntryAssigned;
use App\Services\Notifications\NotifyUser;
use Carbon\CarbonInterface;

/*
 * Puts the next thing to do on a matter in someone's diary. Whoever it is for is
 * told, unless they diarised it themselves.
 */
class DiariseNextAction
{
    public function __construct(private readonly NotifyUser $notify) {}

    public function diarise(
        Matter $matter,
        User $assignee,
        string $body,
        CarbonInterface $dueAt,
        ?User $diarisedBy = null,
        ?int $remindBeforeMinutes = null,
    ): DiaryEntry {
        $entry = new DiaryEntry([
            'body' => $body,
            'due_at' => $dueAt,
            'remind_before_minutes' => $remindBeforeMinutes,
        ]);

        $entry->matter()->associate($matter);
        $entry->assignee()->associate($assignee);
        $entry->save();

        if ($diarisedBy !== null && ! $assignee->is($diarisedBy)) {
            $this->notify->about($assignee, new DiaryEntryAssigned($entry, $diarisedBy));
        }

        return $entry;
    }
}
