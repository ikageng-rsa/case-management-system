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
    case EditMatterDetails = 'edit_matter_details';
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

    // The people of the firm, one power at a time: seeing them, keeping their
    // record, inviting them, giving them roles, and shaping what a role is.
    case ViewUsers = 'view_users';
    case EditUsers = 'edit_users';
    case InviteUsers = 'invite_users';
    case DeactivateUsers = 'deactivate_users';
    case AssignRoles = 'assign_roles';
    case ManageRoles = 'manage_roles';

    case ManageMatterTypes = 'manage_matter_types';
    case ManageActivityTypes = 'manage_activity_types';
    case ManageCourts = 'manage_courts';
    case ManageTariffs = 'manage_tariffs';
    case ManageBillingSettings = 'manage_billing_settings';
    case ManageFirmSettings = 'manage_firm_settings';
    // Billing on every matter, without the file.
    case AccessAllBilling = 'access_all_billing';
    // Seeing amounts: work value, bills, payments and balances.
    case ViewBills = 'view_bills';
    case ManageFeeAgreements = 'manage_fee_agreements';
    // Billing a client or the other side: drawing a bill, issuing it, and voiding one issued in error.
    case DraftBills = 'draft_bills';
    case IssueBills = 'issue_bills';
    case VoidBills = 'void_bills';
    // Recording money received on an issued bill, and reversing a payment recorded in error.
    case RecordPayments = 'record_payments';
    case ReversePayments = 'reverse_payments';
    // Seeing what is owed to the firm across the matters a person may see.
    case ViewBillingReports = 'view_billing_reports';
    case ViewAuditLog = 'view_audit_log';
    case ExportAuditLog = 'export_audit_log';
}
