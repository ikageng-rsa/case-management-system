<?php

declare(strict_types=1);

namespace App\Queries\Matters;

use App\Enums\Matter\Assignment;
use App\Models\Matter\Matter;
use App\Models\User\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class MatterList
{
    /** @return array{matters: LengthAwarePaginator<int, Matter>, counts: array<string, int>} */
    public function for(User $user, string $filter, string $sort, ?int $matterTypeId, ?string $search = null): array
    {
        $visible = fn (): Builder => Matter::query()
            ->when(! $user->can('viewAny', Matter::class), fn (Builder $query) => $query->assignedTo($user));

        $unassigned = fn (Builder $query) => $query->whereDoesntHave(
            'assignments',
            fn (Builder $query) => $query->active()->inCapacity(Assignment::Responsible),
        );

        $matters = $visible()
            ->when($matterTypeId, fn (Builder $query) => $query->where('matter_type_id', $matterTypeId))
            // A search orders its matches by how close they are, ahead of the chosen sort.
            ->when($search, fn (Builder $query) => $query->search($search))
            ->tap(fn (Builder $query) => match ($filter) {
                'mine' => $query->open()->assignedTo($user),
                'unassigned' => $unassigned($query->open()),
                'closed' => $query->closed(),
                default => $query->open(),
            })
            ->with([
                'client',
                'matterType',
                'assignments.user',
                'diaryEntries' => fn ($query) => $query->pending(),
            ])
            ->withCount('media')
            ->tap(fn (Builder $query) => match ($sort) {
                'opened' => $query->latest('instructed_at'),
                'reference' => $query->orderBy('reference'),
                // Matters with no pending diary entry sort last.
                default => $query
                    ->withMin(['diaryEntries as next_due_at' => fn ($query) => $query->pending()], 'due_at')
                    ->orderByRaw('next_due_at is null')
                    ->orderBy('next_due_at'),
            })
            ->paginate();

        return [
            'matters' => $matters,
            'counts' => [
                'open' => $visible()->open()->count(),
                'mine' => $visible()->open()->assignedTo($user)->count(),
                'unassigned' => $unassigned($visible()->open())->count(),
                'closed' => $visible()->closed()->count(),
                'closed_this_year' => $visible()->closed()->whereYear('closed_at', now()->year)->count(),
            ],
        ];
    }
}
