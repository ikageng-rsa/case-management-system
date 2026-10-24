<?php

declare(strict_types=1);

namespace App\Services\Roles;

use App\Models\User\Role;
use Illuminate\Support\Facades\DB;

class DeleteRole
{
    public function __construct(private readonly EnsureAdministrationRemains $administration) {}

    public function delete(Role $role): void
    {
        $holders = $role->users()->count();

        if ($holders > 0) {
            throw new CannotDeleteRoleInUse($role, $holders);
        }

        DB::transaction(function () use ($role): void {
            $role->delete();

            $this->administration->check();
        });
    }
}
