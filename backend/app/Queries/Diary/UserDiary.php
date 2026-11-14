<?php

declare(strict_types=1);

namespace App\Queries\Diary;

use App\Models\Diary\DiaryEntry;
use App\Models\Matter\Matter;
use App\Models\User\User;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/*
 * Someone's own diary across every matter, for one view and an optional run of
 * days, with how many entries each view holds. Only matters they may still see
 * are counted, so an entry left behind on a file they have come off does not
 * show the file to them.
 */
class UserDiary
{
    private const PER_PAGE = 25;

    /** @return array{entries: LengthAwarePaginator<int, DiaryEntry>, counts: array<string, int>} */
    public function for(User $user, string $show, ?CarbonInterface $from, ?CarbonInterface $to): array
    {
        $own = fn (): Builder => DiaryEntry::query()
            ->assignedTo($user)
            ->when(! $user->can('viewAny', Matter::class), fn (Builder $query) => $query->whereHas(
                'matter',
                fn (Builder $matter) => $matter->assignedTo($user),
            ));

        $entries = $own()
            ->when($from, fn (Builder $query) => $query->where('due_at', '>=', $from->copy()->startOfDay()))
            ->when($to, fn (Builder $query) => $query->where('due_at', '<=', $to->copy()->endOfDay()))
            ->tap(fn (Builder $query) => match ($show) {
                'overdue' => $query->overdue()->orderBy('due_at'),
                // What was done most recently comes first.
                'completed' => $query->completed()->latest('completed_at'),
                default => $query->pending()->orderBy('due_at'),
            })
            ->with(['matter.assignments', 'assignee', 'completedBy'])
            ->paginate(self::PER_PAGE);

        return [
            'entries' => $entries,
            'counts' => [
                'pending' => $own()->pending()->count(),
                'overdue' => $own()->overdue()->count(),
                'due_today' => $own()->pending()->whereDate('due_at', today())->count(),
                'completed' => $own()->completed()->count(),
            ],
        ];
    }
}
