<?php

declare(strict_types=1);

namespace App\Models\Audit;

use App\Models\Diary\DiaryEntry;
use App\Models\Document\Media;
use App\Models\Matter\Matter;
use App\Models\Matter\MatterAssignment;
use App\Models\Matter\MatterParty;
use App\Models\Narration\Narration;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Spatie\Activitylog\Models\Activity as BaseActivity;

/*
 * People and some records are deactivated or retired rather than removed, and
 * the audit trail has to go on naming them: who did something does not stop
 * being true because they have since left.
 */
class Activity extends BaseActivity
{
    public function causer(): MorphTo
    {
        return $this->morphTo()->withoutGlobalScope(SoftDeletingScope::class);
    }

    /** Narrow the log to one person, one event, or both; null leaves it open. */
    public function scopeFilteredBy(Builder $query, ?string $actor, ?string $action): void
    {
        $query
            ->when($actor, fn (Builder $query, string $actor) => $query->where('causer_id', $actor))
            ->when($action, fn (Builder $query, string $action) => $query->where('event', $action));
    }

    /** The matter the entry is about, reached through whatever its subject is. */
    public function belongingMatter(): ?Matter
    {
        $subject = $this->subject;

        return match (true) {
            $subject instanceof Matter => $subject,
            $subject instanceof Narration, $subject instanceof DiaryEntry, $subject instanceof MatterAssignment, $subject instanceof MatterParty => $subject->matter,
            $subject instanceof Media => $subject->model instanceof Matter ? $subject->model : null,
            default => null,
        };
    }
}
