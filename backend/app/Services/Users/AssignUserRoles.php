<?php

declare(strict_types=1);

namespace App\Services\Users;

use App\Models\User;
use App\Services\Roles\EnsureAdministrationRemains;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

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
            // Ids arrive from a form as strings, and a string given to syncRoles is read
            // as a role's name, so the roles are looked up and handed over as themselves.
            $user->syncRoles(Role::query()->whereIn('id', $roles)->get());

            $this->administration->check();
        });

        return $user;
    }
}
