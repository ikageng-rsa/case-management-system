<?php

declare(strict_types=1);

namespace App\Enums\Auth;

/**
 * Capabilities that are not tied to a single matter.
 *
 * Work a user does on a matter they are assigned to — narrating their own
 * time, reading the file — is governed by the assignment, not by a permission.
 */
enum Permission: string
{
    case ViewAllMatters = 'view_all_matters';
    case CreateMatters = 'create_matters';
    case EditMatters = 'edit_matters';
    case CloseMatters = 'close_matters';
    case ReopenMatters = 'reopen_matters';
    case ArchiveMatters = 'archive_matters';
    case AssignMatters = 'assign_matters';
    case TransferMatters = 'transfer_matters';

    case ViewClients = 'view_clients';
    case CreateClients = 'create_clients';
    case EditClients = 'edit_clients';
    case DeleteClients = 'delete_clients';
    case MergeClients = 'merge_clients';
    case ViewClientContacts = 'view_client_contacts';
    case ViewUnmaskedClientData = 'view_unmasked_client_data';

    case ViewPopiaConsents = 'view_popia_consents';
    case RecordPopiaConsent = 'record_popia_consent';
    case WithdrawPopiaConsent = 'withdraw_popia_consent';
    case ExportClientData = 'export_client_data';

    case EditAnyNarrations = 'edit_any_narrations';
    case DeleteNarrations = 'delete_narrations';

    case DeleteDocuments = 'delete_documents';

    case AmendLedgerEntries = 'amend_ledger_entries';

    case ManageUsers = 'manage_users';
    case AssignRoles = 'assign_roles';
    case ManageMatterTypes = 'manage_matter_types';
    case ManageActivityTypes = 'manage_activity_types';
    case ManageCourts = 'manage_courts';
    case ViewAuditLog = 'view_audit_log';
    case ExportAuditLog = 'export_audit_log';
}
