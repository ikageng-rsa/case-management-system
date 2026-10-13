<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Auth\Permission;
use App\Models\Client;
use App\Models\User;

class ClientPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ViewClients->value);
    }

    public function view(User $user, Client $client): bool
    {
        return $user->can(Permission::ViewClients->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::CreateClients->value);
    }

    public function update(User $user, Client $client): bool
    {
        return $user->can(Permission::EditClients->value);
    }
}
