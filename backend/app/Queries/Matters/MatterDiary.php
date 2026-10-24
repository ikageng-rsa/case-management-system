<?php

declare(strict_types=1);

namespace App\Queries\Matters;

use App\Models\Matter\Matter;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Builder;

/*
 * The diary entries on a matter's diary tab, soonest due first, for one view
 * (pending, completed, overdue, or the user's own pending), with how many
 * entries each view holds.
 */
class MatterDiary
{
    /** @return array<string, mixed> */
    public function for(Matter $matter, User $user, string $show): array
    {
        $entries = $matter->diaryEntries()
            ->with(['assignee', 'completedBy'])
            ->when($show === 'pending', fn (Builder $query) => $query->pending())
            ->when($show === 'completed', fn (Builder $query) => $query->completed())
            ->when($show === 'overdue', fn (Builder $query) => $query->overdue())
            ->when($show === 'mine', fn (Builder $query) => $query->pending()->assignedTo($user))
            ->orderBy('due_at')
            ->get();

        return [
            'entries' => $entries,
            'show' => $show,
            'diaryCounts' => [
                'pending' => $matter->diaryEntries()->pending()->count(),
                'completed' => $matter->diaryEntries()->completed()->count(),
                'overdue' => $matter->diaryEntries()->overdue()->count(),
                'mine' => $matter->diaryEntries()->pending()->assignedTo($user)->count(),
            ],
        ];
    }
}
