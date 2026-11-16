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
  // --- from the v1 API (diary-entries) ---
  /** What has to be done, as written on the entry. `title` and `nextAction` show the same words. */
  body?: string;
  /** The exact due moment (ISO). `dueDate` is its calendar day. */
  dueAt?: string;
  /** True once the due time has been reached (an entry can only be marked done from then on). */
  isDue?: boolean;
  assigneeId?: string;
  assigneeName?: string;
  /** Minutes before `dueAt` that the reminder goes out; null = no reminder. */
  remindBeforeMinutes?: number | null;
  reminderLabel?: string;
  completedAt?: string | null;
  completedById?: string | null;
  completedByName?: string;
  /** The narration (work done) this entry is linked to, if any. */
  narrationId?: string | null;
  matterReference?: string;
  matterTitle?: string;
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
