<?php

declare(strict_types=1);

namespace App\Services\Matters;

use App\Enums\Matter\Assignment;
use App\Models\Matter\Matter;
use App\Models\Matter\MatterAssignment;
use App\Models\User;
use App\Notifications\MatterAssigned;
use App\Services\Notifications\NotifyUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AssignUserToMatter
{
    public function __construct(private readonly NotifyUser $notify) {}

    public function assign(Matter $matter, User $user, Assignment $capacity): MatterAssignment
    {
        if ($matter->isClosed()) {
            throw new CannotAssignToClosedMatter($matter);
        }

        $assigned = false;

        $assignment = DB::transaction(function () use ($matter, $user, $capacity, &$assigned): MatterAssignment {
            $existing = $matter->assignments()
                ->active()
                ->inCapacity($capacity)
                ->where('user_id', $user->getKey())
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $assigned = true;

            // Responsible and Supervising are held by one person at a time, so
            // taking the seat retires whoever currently holds it.
            if ($capacity->isExclusive()) {
                $matter->assignments()->active()->inCapacity($capacity)->get()
                    ->each->unassign();
            }

            $assignment = $matter->assignments()->make(['capacity' => $capacity]);
            $assignment->user()->associate($user);
            $assignment->save();

            $matter->unsetRelation('assignments');

            return $assignment;
        });

        // Told after it is done, and only when something changed and it was not their own doing.
        if ($assigned && ! $user->is(Auth::user())) {
            $this->notify->about($user, new MatterAssigned($matter, $capacity, Auth::user()));
        }

        return $assignment;
    }
}
