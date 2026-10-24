<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Auth\Permission;
use App\Models\Client\Client;
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

    /** A FICA document shows identifiers in full, so reading one is for those cleared to see them. */
    public function downloadFicaDocument(User $user, Client $client): bool
    {
        return $this->view($user, $client) && $user->can(Permission::ViewUnmaskedClientData->value);
    }

    public function deleteFicaDocument(User $user, Client $client): bool
    {
        return $this->update($user, $client) && $user->can(Permission::DeleteDocuments->value);
    }
}
