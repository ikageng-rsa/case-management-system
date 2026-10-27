<?php

declare(strict_types=1);

namespace App\Queries\Matters;

use App\Models\Audit\Activity;
use App\Models\Matter\Matter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/*
 * Everything the audit log holds about a matter, newest first: what was done
 * to the matter itself and to what hangs off it (narrations, diary entries,
 * assignments, parties and documents).
 */
class MatterAuditTrail
{
    /** @return LengthAwarePaginator<int, Activity> */
    public function for(Matter $matter): LengthAwarePaginator
    {
        $related = [
            'narration' => $matter->narrations()->pluck('id'),
            'diary_entry' => $matter->diaryEntries()->pluck('id'),
            'matter_assignment' => $matter->assignments()->pluck('id'),
            'matter_party' => $matter->parties()->pluck('id'),
            'document' => $matter->media()->pluck('id'),
        ];

        return Activity::query()
            ->where(function (Builder $query) use ($matter, $related): void {
                $query->where(fn (Builder $query) => $query
                    ->where('subject_type', $matter->getMorphClass())
                    ->where('subject_id', $matter->getKey()));

                foreach ($related as $type => $ids) {
                    $query->orWhere(fn (Builder $query) => $query
                        ->where('subject_type', $type)
                        ->whereIn('subject_id', $ids->map(fn ($id) => (string) $id)->all()));
                }
            })
            ->with('causer')
            ->latest('id')
            ->paginate(8, ['*'], 'audit_page')
            ->withQueryString();
    }
}
