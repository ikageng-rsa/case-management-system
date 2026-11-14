<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Auth\Permission;
use App\Models\Matter\Matter;
use App\Models\User\User;

class MatterPolicy
{
    /** Whether the user browses every matter, or only the ones they carry. */
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ViewAllMatters->value);
    }

    public function view(User $user, Matter $matter): bool
    {
        return $user->can(Permission::ViewAllMatters->value) || $matter->isAssignedTo($user);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::CreateMatters->value);
    }

    public function update(User $user, Matter $matter): bool
    {
        return $user->can(Permission::EditMatters->value);
    }

    /**
     * The matter's own particulars: its title, dates and court. Kept apart from
     * its parties, only while it is open, and only for those who may see the file.
     */
    public function editDetails(User $user, Matter $matter): bool
    {
        return $matter->isOpen()
            && $user->can(Permission::EditMatterDetails->value)
            && $this->view($user, $matter);
    }

    public function close(User $user, Matter $matter): bool
    {
        return $user->can(Permission::CloseMatters->value);
    }

    /** Undoing a closure is a decision of its own, held by fewer people than closing. */
    public function reopen(User $user, Matter $matter): bool
    {
        return $user->can(Permission::ReopenMatters->value);
    }

    public function assign(User $user, Matter $matter): bool
    {
        return $user->can(Permission::AssignMatters->value);
    }

    /** Work on a file belongs to the people assigned to it, while it is open. */
    public function work(User $user, Matter $matter): bool
    {
        return $matter->isOpen() && $matter->isAssignedTo($user);
    }

    /** How the client is charged: recording, finalising or failing a fee agreement, for those who may see the file. */
    public function manageFees(User $user, Matter $matter): bool
    {
        return $user->can(Permission::ManageFeeAgreements->value) && $this->view($user, $matter);
    }

    /** Drawing a bill on the matter, and changing or deleting it while it is a draft. */
    public function draftBills(User $user, Matter $matter): bool
    {
        return $user->can(Permission::DraftBills->value) && $this->view($user, $matter);
    }

    /** Issuing a checked draft, after which it is owed. */
    public function issueBills(User $user, Matter $matter): bool
    {
        return $user->can(Permission::IssueBills->value) && $this->view($user, $matter);
    }

    /** Voiding a bill issued in error. */
    public function voidBills(User $user, Matter $matter): bool
    {
        return $user->can(Permission::VoidBills->value) && $this->view($user, $matter);
    }

    /** Seeing the matter's bills, for anyone who drafts, issues or voids them. */
    public function viewBills(User $user, Matter $matter): bool
    {
        return $this->draftBills($user, $matter) || $this->issueBills($user, $matter) || $this->voidBills($user, $matter);
    }

    public function deleteDocument(User $user, Matter $matter): bool
    {
        return $user->can(Permission::DeleteDocuments->value) && $this->view($user, $matter);
    }

    public function viewAudit(User $user, Matter $matter): bool
    {
        return $user->can(Permission::ViewAuditLog->value) && $this->view($user, $matter);
    }
}
