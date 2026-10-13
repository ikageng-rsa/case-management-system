<?php

declare(strict_types=1);

namespace App\Services\Roles;

use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class SaveRole
{
    public function __construct(private readonly EnsureAdministrationRemains $administration) {}

    /**
     * Create the role, or rename and re-grant an existing one. Permissions are
     * the fixed set the system defines; a role only chooses among them.
     *
     * @param  array<int, string>  $permissions
     */
    public function save(?Role $role, string $name, array $permissions): Role
    {
        return DB::transaction(function () use ($role, $name, $permissions): Role {
            $role ??= new Role(['guard_name' => 'web']);

            $role->name = trim($name);
            $role->save();
            $role->syncPermissions($permissions);

            $this->administration->check();

            return $role;
        });
    }
}
