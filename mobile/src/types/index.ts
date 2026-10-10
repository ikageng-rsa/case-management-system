export type UserRole = 'Admin' | 'Director' | 'CA' | 'Secretary' | 'Messenger';

export interface User {
  id: string;
  fullName: string;
  email: string;
  role: UserRole;
}

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

export interface ClientProfile {
  id: string;
  fullName: string;
  contactNumber: string;
  email: string;
  ficaVerified: boolean;
  riskRating: 'Low' | 'Medium' | 'High';
}

export interface Narration {
  id: string;
  caseId: string;
  authorId: string;
  activityType: 'Call' | 'Draft' | 'Appearance' | 'Travel' | 'Other';
  description: string;
  billable: boolean;
  tariffCode?: string;
  createdAt: string;
}

export interface Darzation {
  id: string;
  caseId: string;
  ownerId: string;
  nextAction: string;
  nextActionDate: string; // ISO date
  overdue: boolean;
  visibleToRoles: UserRole[];
}

export interface CaseDocument {
  id: string;
  caseId: string;
  fileName: string;
  version: number;
  confidentiality: 'Standard' | 'Restricted' | 'Privileged';
  uploadedById: string;
  uploadedAt: string;
  uri: string;
  cachedOffline?: boolean;
}

export interface BillingEntry {
  id: string;
  caseId: string;
  narrationId?: string;
  tariffScale: string;
  amount: number;
  billed: boolean;
  billedAt?: string;
}
