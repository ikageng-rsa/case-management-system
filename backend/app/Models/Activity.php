<?php

declare(strict_types=1);

namespace App\Models;

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
}
