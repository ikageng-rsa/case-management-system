<?php

declare(strict_types=1);

namespace App\Services\Users;

use App\Models\Role;
use App\Models\User;
use App\Services\Roles\EnsureAdministrationRemains;
use Illuminate\Support\Facades\DB;

class AssignUserRoles
{
    public function __construct(private readonly EnsureAdministrationRemains $administration) {}

    /**
     * Make the user's roles exactly the given ones, touching only what differs.
     * Role changes are recorded by the listener on the package's attach and
     * detach events; syncing would report every role the person keeps as
     * revoked and granted again, so only the real changes are made.
     *
     * @param  array<int, int|string>  $roles  Role ids
     */
    public function assign(User $user, array $roles): User
    {
        DB::transaction(function () use ($user, $roles): void {
            // Ids arrive from a form as strings, and a string given to the package is
            // read as a role's name, so the roles are looked up and handed over as themselves.
            $wanted = Role::query()->whereIn('id', $roles)->get();
            $current = $user->roles()->get();

            $dropped = $current->diff($wanted);
            $added = $wanted->diff($current);

            if ($dropped->isNotEmpty()) {
                $user->removeRole($dropped);
            }

            if ($added->isNotEmpty()) {
                $user->assignRole($added);
            }

            $this->administration->check();
        });

        return $user;
    }
}
