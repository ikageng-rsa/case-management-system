<?php

declare(strict_types=1);

namespace App\Services\Users;

use App\Models\User;
use App\Services\Roles\EnsureAdministrationRemains;
use Illuminate\Support\Facades\DB;

class AssignUserRoles
{
    public function __construct(private readonly EnsureAdministrationRemains $administration) {}

    /**
     * Replace the user's roles with the given ones. Role changes are recorded
     * by the listener on Spatie's attach and detach events, not here.
     *
     * @param  array<int, int|string>  $roles  Role ids
     */
    public function assign(User $user, array $roles): User
    {
        DB::transaction(function () use ($user, $roles): void {
            $user->syncRoles($roles);

            $this->administration->check();
        });

        return $user;
    }
}
