<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Auth\Permission;
use App\Models\Matter;
use App\Models\User;

class MatterPolicy
{
    /** Whether the user browses every matter, or only the ones they carry. */
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ViewAllMatters->value);
    }

    public function view(User $user, Matter $matter): bool
    {
        return $user->can(Permission::ViewAllMatters->value) || $matter->isAssignedTo($user);
    }
}
