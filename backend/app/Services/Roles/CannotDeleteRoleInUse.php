<?php

declare(strict_types=1);

namespace App\Services\Roles;

use App\Models\Role;
use RuntimeException;

class CannotDeleteRoleInUse extends RuntimeException
{
    public function __construct(public readonly Role $role, public readonly int $users)
    {
        parent::__construct(sprintf(
            'The %s role is held by %d %s. Move them to another role first.',
            $role->name,
            $users,
            $users === 1 ? 'user' : 'users',
        ));
    }
}
