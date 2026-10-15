<?php

declare(strict_types=1);

namespace App\Services\Roles;

use App\Models\Role;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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

            $before = $role->exists ? $role->permissions()->pluck('name')->all() : [];

            $role->name = trim($name);
            $role->save();
            $role->syncPermissions($permissions);

            $this->administration->check();

            $this->recordPermissionChange($role, $before);

            return $role;
        });
    }

    /**
     * Said as what changed, in words that name the permissions, so the log reads
     * as a decision: this role may now do this, and may no longer do that. The
     * package reports a re-save as everything revoked and granted again, which
     * would make every save look like a change.
     *
     * @param  array<int, string>  $before
     */
    private function recordPermissionChange(Role $role, array $before): void
    {
        $after = $role->permissions()->pluck('name')->all();

        $granted = array_values(array_diff($after, $before));
        $revoked = array_values(array_diff($before, $after));

        if ($granted === [] && $revoked === []) {
            return;
        }

        $role->recordEvent(
            'permissions_changed',
            sprintf('%s changed what the %s role may do', Auth::user()?->name ?? 'System', $role->name),
            ['granted' => $granted, 'revoked' => $revoked],
        );
    }
}
