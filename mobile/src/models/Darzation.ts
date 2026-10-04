import { UserRole } from './User';

export interface Darzation {
  id: string;
  caseId: string;
  ownerId: string;
  nextAction: string;
  nextActionDate: string; // ISO date
  overdue: boolean;
  visibleToRoles: UserRole[];
}
