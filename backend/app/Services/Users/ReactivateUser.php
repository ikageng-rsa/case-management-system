<?php

declare(strict_types=1);

namespace App\Services\Users;

use App\Models\User;

/*
 * Brings a person back with the roles they had. What was handed over when they
 * were deactivated stays where it was put: they start with a clean desk.
 */
class ReactivateUser
{
    public function reactivate(User $user, User $by): void
    {
        $user->restore();

        $user->recordEvent('reactivated', $by->name.' reactivated user '.$user->name);
    }
}
