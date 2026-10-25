<?php

declare(strict_types=1);

namespace App\Queries\Audit;

use App\Models\Audit\Activity;
use Illuminate\Support\LazyCollection;

/*
 * The filtered audit log as rows for a spreadsheet, read a few hundred at a
 * time so a long log can be downloaded without holding it all in memory.
 */
class AuditLogExport
{
    public const HEADINGS = ['When', 'Actor', 'Action', 'Description', 'Matter', 'IP'];

    /** @return LazyCollection<int, list<string|null>> */
    public function for(?string $actor, ?string $action): LazyCollection
    {
        return Activity::query()
            ->filteredBy($actor, $action)
            ->with(['causer', 'subject'])
            ->latest('id')
            ->lazyById(500)
            ->map(fn (Activity $activity): array => [
                $activity->created_at->format('Y-m-d H:i:s'),
                $activity->causer?->name ?? 'System',
                $activity->event,
                $activity->description,
                $activity->belongingMatter()?->reference,
                $activity->properties['ip'] ?? null,
            ]);
    }
}
