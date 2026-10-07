export type CaseStatus = 'Open' | 'In Progress' | 'On Hold' | 'Closed' | 'Archived';

export interface Case {
  id: string;
  fileReference: string; // initials+mattertype+seq+year
  clientId: string;
  clientName: string;
  matterType: string;
  status: CaseStatus;
  leadAttorneyId: string;
  openedDate: string; // ISO date
  opposingParty?: string;
}
