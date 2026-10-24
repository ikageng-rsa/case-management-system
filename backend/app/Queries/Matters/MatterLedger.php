<?php

declare(strict_types=1);

namespace App\Queries\Matters;

use App\Models\Matter;
use App\Models\Narration;
use App\Models\User;

/*
 * The narrations on a matter's ledger tab, newest first, narrowed to the
 * author's own or to one activity code, with the counts behind each filter.
 */
class MatterLedger
{
    /** @return array<string, mixed> */
    public function for(Matter $matter, User $user, ?string $filter): array
    {
        $all = $matter->narrations()->with(['activityType', 'author'])->latestFirst()->get();

        $narrations = match (true) {
            $filter === 'mine' => $all->where('author_id', $user->getKey()),
            filled($filter) => $all->filter(fn (Narration $narration) => $narration->activityType->code === $filter),
            default => $all,
        };

        $filters = $all->groupBy(fn (Narration $narration) => $narration->activityType->code)
            ->map(fn ($group) => ['label' => strtolower($group->first()->activityType->name), 'count' => $group->count()]);

        return [
            'narrations' => $narrations->values(),
            'allCount' => $all->count(),
            'filter' => $filter,
            'filters' => $filters,
            'mineCount' => $all->where('author_id', $user->getKey())->count(),
            'minutesRecorded' => (float) $all
                ->filter(fn (Narration $narration) => $narration->activityType->measure->isTimed())
                ->sum('quantity'),
        ];
    }
}
