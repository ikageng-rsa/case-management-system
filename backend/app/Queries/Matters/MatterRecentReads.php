<?php

declare(strict_types=1);

namespace App\Queries\Matters;

use App\Models\Audit\Activity;
use App\Models\Matter\Matter;
use Illuminate\Database\Eloquent\Collection;

/*
 * The last few times someone opened a matter, newest first, so whoever is on
 * it can see who else has been looking.
 */
class MatterRecentReads
{
    /** @return Collection<int, Activity> */
    public function for(Matter $matter, int $limit = 3): Collection
    {
        return Activity::query()
            ->where('subject_type', $matter->getMorphClass())
            ->where('subject_id', $matter->getKey())
            ->where('event', 'read')
            ->with('causer')
            ->latest('id')
            ->limit($limit)
            ->get();
    }
}
