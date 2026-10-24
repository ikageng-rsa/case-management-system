<?php

declare(strict_types=1);

namespace App\Services\Roles;

use App\Enums\Auth\Permission;
use App\Models\User\User;

/*
 * Roles can be reshaped by the people who hold them, so a change must never
 * leave the firm without anyone able to undo it: somebody must always be able
 * to give people roles and to say what a role means. Called inside the transaction
 * that makes the change, so throwing rolls it back.
 */
class EnsureAdministrationRemains
{
    public function check(): void
    {
        foreach ([Permission::AssignRoles, Permission::ManageRoles] as $permission) {
            if (User::query()->permission($permission->value)->doesntExist()) {
                throw new CannotRemoveLastAdministrator;
            }
        }
    }
}
