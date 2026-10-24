<?php

declare(strict_types=1);

namespace App\Services\Matters;

use App\Enums\Matter\Assignment;
use App\Models\Matter\Matter;
use App\Models\User;
use App\Notifications\MatterUnassigned;
use App\Services\Notifications\NotifyUser;
use Illuminate\Support\Facades\Auth;

class UnassignUserFromMatter
{
    public function __construct(private readonly NotifyUser $notify) {}

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

        if ($assignment !== null && ! $user->is(Auth::user())) {
            $this->notify->about($user, new MatterUnassigned($matter, $capacity, Auth::user()));
        }
    }
}
