<?php

declare(strict_types=1);

namespace App\Listeners;

use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Events\RoleAttachedEvent;
use Spatie\Permission\Events\RoleDetachedEvent;
use Spatie\Permission\Models\Role;

class RecordRoleChange
{
    public function handle(RoleAttachedEvent|RoleDetachedEvent $event): void
    {
        $granted = $event instanceof RoleAttachedEvent;

        foreach ($this->roleNames($event->rolesOrIds) as $role) {
            activity('user')
                ->performedOn($event->model)
                ->withProperties(['role' => $role])
                ->event($granted ? 'role_granted' : 'role_revoked')
                ->log(sprintf(
                    '%s %s the %s role %s %s',
                    Auth::user()?->name ?? 'System',
                    $granted ? 'granted' : 'revoked',
                    $role,
                    $granted ? 'to' : 'from',
                    $event->model->name,
                ));
        }
    }

    /**
     * The trait hands over roles, names or ids depending on how they were assigned.
     *
     * @return array<int, string>
     */
    protected function roleNames(mixed $rolesOrIds): array
    {
        return collect($rolesOrIds)
            ->map(fn (mixed $role) => $role instanceof Role
                ? $role->name
                : Role::find($role)?->name ?? (string) $role)
            ->all();
    }
}
