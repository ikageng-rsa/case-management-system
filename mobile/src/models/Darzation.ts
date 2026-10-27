import { UserRole } from './User';

export type DarzationPriority = 'Low' | 'Medium' | 'High' | 'Urgent';
export type DarzationStatus = 'Pending' | 'In Progress' | 'Done';

export interface DarzationStep {
  id: string;
  label: string;
  done: boolean;
}

export interface Darzation {
  id: string;
  caseId: string;
  ownerId: string;
  title: string;
  nextAction: string;
  nextActionDate: string; // ISO date — kept for backward compatibility with existing screens/API
  dueDate: string; // ISO date — the actual deadline overdue logic is computed from
  priority: DarzationPriority;
  status: DarzationStatus;
  steps: DarzationStep[];
  assignedUserIds: string[];
  overdue: boolean;
  reminderSentAt?: string; // when the overdue SMS/push reminder last fired, so we don't re-notify every poll
  visibleToRoles: UserRole[];
  /** Set only on records created/edited offline that the server hasn't confirmed yet (never sent to the API). */
  syncState?: 'pending' | 'failed';
}

/** A step-complete diary item is never overdue, however old its due date. */
export function computeOverdue(dueDate: string, status: DarzationStatus, now: Date = new Date()): boolean {
  if (status === 'Done') {
    return false;
  }
  return new Date(dueDate).getTime() < now.getTime();
}
