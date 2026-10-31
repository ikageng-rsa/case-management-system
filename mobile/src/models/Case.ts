export type CaseStatus = 'Open' | 'In Progress' | 'On Hold' | 'Closed' | 'Archived';
export type CasePriority = 'Low' | 'Medium' | 'High' | 'Urgent';


//What the signed-in user do on one matter 
export interface CaseAbilities{
  work?: boolean;
  assign?: boolean;
  view_audit?:boolean;
}

export interface CaseAssignment{
  id: string;
  userId: string;
  userName?: string;
  capacity: string;
  assignedAt?: string;
}
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
  // from the v1 API(matters)
  //Matter title, e.g "Claim against the Road Accident Fund"
  title?: string;
  //The api's raw status word (e.g "Open")
  rawStatus?: string;
  prescribesAt?: string; //ISO-the date the claim prescribes
  nextDueAt?: string; //ISO - next deadline on the matter
  //The signed-in user's capacities on this matter (responsible / supervising/assisting/messenger)
  myCapacities?: string[];
  documentsCount?: number;
  courtLabel?: string;
  matterTypeCode?: string;
  can?: CaseAbilities;
  assignments?: CaseAssignment[];
  /** Set only on records created/edited offline that the server hasn't confirmed yet (never sent to the API). */
  syncState?: 'pending' | 'failed';
}
