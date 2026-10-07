<?php

declare(strict_types=1);

namespace App\Services\Matters;

use App\Enums\Matter\Assignment;
use App\Models\Matter;
use App\Models\User;

class UnassignUserFromMatter
{
    public function unassign(Matter $matter, User $user, Assignment $capacity): void
    {
        $assignment = $matter->assignments()
            ->active()
            ->inCapacity($capacity)
            ->where('user_id', $user->getKey())
            ->first();

        // Nothing to do when the user does not currently hold the capacity.
        $assignment?->unassign();

        $matter->unsetRelation('assignments');
    }
}
