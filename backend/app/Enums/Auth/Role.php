<?php

namespace App\Enums\Auth;

enum Role: string
{
    case Director = 'director';
    case Attorney = 'attorney';
    case CandidateAttorney = 'candidate_attorney';
    case Secretary = 'legal_secretary';
    case Manager = 'manager';
    case Messenger = 'messenger';

    /**
     * @return array<int, Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Director => Permission::cases(),
            self::Attorney => [
                Permission::CreateMatters,
                Permission::EditMatters,
                Permission::CloseMatters,
                Permission::AssignMatters,
                Permission::ViewClients,
                Permission::CreateClients,
                Permission::EditClients,
                Permission::ViewClientContacts,
                Permission::ViewUnmaskedClientData,
                Permission::ViewPopiaConsents,
                Permission::RecordPopiaConsent,
                Permission::WithdrawPopiaConsent,
                Permission::ViewAuditLog,
            ],
            self::CandidateAttorney => [
                Permission::ViewClients,
                Permission::ViewClientContacts,
                Permission::ViewUnmaskedClientData,
                Permission::ViewPopiaConsents,
            ],
            self::Secretary => [
                Permission::ViewAllMatters,
                Permission::CreateMatters,
                Permission::EditMatters,
                Permission::ViewClients,
                Permission::CreateClients,
                Permission::EditClients,
                Permission::ViewClientContacts,
                Permission::ViewUnmaskedClientData,
                Permission::ViewPopiaConsents,
                Permission::RecordPopiaConsent,
            ],
            self::Manager => [
                Permission::ViewAllMatters,
                Permission::ViewClients,
                Permission::ManageUsers,
                Permission::AssignRoles,
                Permission::ManageMatterTypes,
                Permission::ManageActivityTypes,
                Permission::ManageCourts,
                Permission::ViewAuditLog,
                Permission::ExportAuditLog,
            ],
            self::Messenger => [],
        };
    }
}
