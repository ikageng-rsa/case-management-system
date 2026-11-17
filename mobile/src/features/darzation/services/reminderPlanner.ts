import { Darzation } from '@/models/index';
import { ScheduledReminder } from '@/services/notifications/localNotifications';

export const REMINDER_PREFIX = 'diary:';
const MAX_REMINDERS = 60; // iOS keeps only the 64 soonest local notifications
const REMINDER_HOUR = 8; // local time

/**
 * Which local reminders to schedule, from the entries' exact due times (pure, so it can be tested):
 *  - "due soon" at the due time minus the entry's own reminder (`remind_before_minutes`), when it has one;
 *  - "overdue" at the due time itself.
 * Entries without an exact time fall back to 08:00 on the due day. Finished entries get none; past times are
 * dropped. Titles stay generic on purpose — a client name should not appear on a lock screen.
 */
function dueMoment(item: Darzation): Date | null {
  if (item.dueAt) {
    const d = new Date(item.dueAt);
    return Number.isNaN(d.getTime()) ? null : d;
  }
  const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(item.dueDate || item.nextActionDate || '');
  return m ? new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3]), REMINDER_HOUR, 0, 0, 0) : null;
}

export function planReminders(items: Darzation[], now: Date = new Date()): ScheduledReminder[] {
  const plans: ScheduledReminder[] = [];
  for (const item of items) {
    if ((item.status ?? 'Pending') === 'Done' || item.id.startsWith('tmp_')) {
      continue;
    }
    const due = dueMoment(item);
    if (!due) {
      continue;
    }
    const data = { caseId: item.caseId, darzationId: item.id };
    const minutes = item.remindBeforeMinutes;
    const remindAt = typeof minutes === 'number' ? new Date(due.getTime() - minutes * 60_000) : null;
    if (remindAt && remindAt > now) {
      plans.push({ identifier: `${REMINDER_PREFIX}${item.id}:due`, title: 'Diary entry due soon', body: 'Open LegalCMS to see what is due.', date: remindAt, data });
    }
    if (due > now) {
      plans.push({ identifier: `${REMINDER_PREFIX}${item.id}:overdue`, title: 'Diary entry is due', body: 'Open LegalCMS to see what is due.', date: due, data });
    }
  }
  return plans.sort((a, b) => a.date.getTime() - b.date.getTime()).slice(0, MAX_REMINDERS);
}
