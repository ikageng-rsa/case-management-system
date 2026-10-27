export type CaseStatus = 'Open' | 'In Progress' | 'On Hold' | 'Closed' | 'Archived';
export type CasePriority = 'Low' | 'Medium' | 'High' | 'Urgent';

export interface Case {
  id: string;
  fileReference: string; // initials+mattertype+seq+year
  clientId: string;
  clientName: string;
  matterType: string;
  status: CaseStatus;
  priority: CasePriority;
  leadAttorneyId: string;
  // Every user who may see/work this case. Director/Admin see every case
  // regardless of this list — see hasGlobalVisibility() in @constants/roles —
  // every other role is scoped to cases where their id appears here.
  assignedUserIds: string[];
  openedDate: string; // ISO date
  closedDate?: string; // ISO date, set when status moves to Closed/Archived
  courtCaseNumber?: string;
  practiceArea?: string;
  issueDescription?: string;
  opposingParty?: string;
  opposingAttorney?: string;
  jurisdiction?: string;
  estimatedValue?: number;
  nextCourtDate?: string; // ISO date
  notes?: string;
  // Free-form metadata the firm wants tracked but that doesn't warrant its own
  // column yet (e.g. "Referral source": "Existing client").
  customFields?: Record<string, string>;
  /** Set only on records created/edited offline that the server hasn't confirmed yet (never sent to the API). */
  syncState?: 'pending' | 'failed';
}
