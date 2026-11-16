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

    /*
     * The money on a matter is reached apart from its file: by someone who may
     * see the file, or by someone who works on billing across every matter and
     * never opens one. Each billing ability is a permission within that reach.
     */
    protected function inBillingScope(User $user, Matter $matter): bool
    {
        return $user->can(Permission::AccessAllBilling->value) || $this->view($user, $matter);
    }

    /** How the client is charged: recording, finalising or failing a fee agreement, and the costs paid out. */
    public function manageFees(User $user, Matter $matter): bool
    {
        return $user->can(Permission::ManageFeeAgreements->value) && $this->inBillingScope($user, $matter);
    }

    /** Seeing the amounts on the matter: the value of its work, its bills, their payments and what is owed. */
    public function viewBills(User $user, Matter $matter): bool
    {
        return $user->can(Permission::ViewBills->value) && $this->inBillingScope($user, $matter);
    }

    /** Drawing a bill on the matter, and changing or deleting it while it is a draft. */
    public function draftBills(User $user, Matter $matter): bool
    {
        return $user->can(Permission::DraftBills->value) && $this->inBillingScope($user, $matter);
    }

    /** Issuing a checked draft, after which it is owed. */
    public function issueBills(User $user, Matter $matter): bool
    {
        return $user->can(Permission::IssueBills->value) && $this->inBillingScope($user, $matter);
    }

    /** Voiding a bill issued in error. */
    public function voidBills(User $user, Matter $matter): bool
    {
        return $user->can(Permission::VoidBills->value) && $this->inBillingScope($user, $matter);
    }

    /** Recording a payment on an issued bill. */
    public function recordPayments(User $user, Matter $matter): bool
    {
        return $user->can(Permission::RecordPayments->value) && $this->inBillingScope($user, $matter);
    }

    /** Reversing a payment recorded in error: undoing is held apart from doing. */
    public function reversePayments(User $user, Matter $matter): bool
    {
        return $user->can(Permission::ReversePayments->value) && $this->inBillingScope($user, $matter);
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
