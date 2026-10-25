<?php

declare(strict_types=1);

namespace App\Queries\Narrations;

use App\Models\Matter\Matter;
use App\Models\Narration\Narration;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/*
 * The latest work put on the ledger, newest first. Someone who may not see
 * every matter sees only what they wrote themselves.
 */
class RecentNarrations
{
    /** @return Collection<int, Narration> */
    public function for(User $user, int $limit = 4): Collection
    {
        return Narration::query()
            ->when(! $user->can('viewAny', Matter::class), fn (Builder $query) => $query->by($user))
            ->with(['matter', 'author'])
            ->latestFirst()
            ->limit($limit)
            ->get();
    }
}
