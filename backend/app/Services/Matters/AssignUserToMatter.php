<?php

declare(strict_types=1);

namespace App\Services\Matters;

use App\Enums\Matter\Assignment;
use App\Models\Matter;
use App\Models\MatterAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AssignUserToMatter
{
    public function assign(Matter $matter, User $user, Assignment $capacity): MatterAssignment
    {
        if ($matter->isClosed()) {
            throw new CannotAssignToClosedMatter($matter);
        }

        return DB::transaction(function () use ($matter, $user, $capacity): MatterAssignment {
            $existing = $matter->assignments()
                ->active()
                ->inCapacity($capacity)
                ->where('user_id', $user->getKey())
                ->first();

            if ($existing !== null) {
                return $existing;
            }

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
    }
}
