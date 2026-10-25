import { Darzation } from '@/models/index';
import { ScheduledReminder } from '@/services/notifications/localNotifications';

export const REMINDER_PREFIX = 'diary:';
const MAX_REMINDERS = 60; // iOS keeps only the 64 soonest local notifications
const REMINDER_HOUR = 8; // local time

/** Local-time 08:00 on the given calendar day (dueDate is "YYYY-MM-DD…"; parsed by hand to avoid UTC shifts). */
function morningOf(isoDate: string, plusDays = 0): Date | null {
    const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(isoDate);
    if (!m) {
        return null;
    }
    return new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3]) + plusDays, REMINDER_HOUR, 0, 0, 0);
}

/**
 * Which local reminders to schedule: "due today" at 08:00 on the due date and "overdue" at 08:00 the morning
 * after. Pure (no device APIs) so it can be tested. Finished items get none; past times are dropped.
 * Titles stay generic on purpose — a client name should not appear on a lock screen.
 */
export function planReminders(items: Darzation[], now: Date = new Date()): ScheduledReminder[] {
    const plans: ScheduledReminder[] = [];
    for (const item of items) {
        if ((item.status ?? 'Pending') === 'Done' || item.id.startsWith('tmp_')) {
            continue;
        }
        const due = item.dueDate || item.nextActionDate;
        const data = { caseId: item.caseId, darzationId: item.id };
        const dueMorning = morningOf(due);
        const overdueMorning = morningOf(due, 1);
        if (dueMorning && dueMorning > now) {
            plans.push({ identifier: `${REMINDER_PREFIX}${item.id}:due`, title: 'Diary item due today', body: 'Open LegalCMS to see what is due.', date: dueMorning, data });
        }
        if (overdueMorning && overdueMorning > now) {
            plans.push({ identifier: `${REMINDER_PREFIX}${item.id}:overdue`, title: 'Diary item overdue', body: 'Open LegalCMS to see what is overdue.', date: overdueMorning, data });
        }
    }
    return plans.sort((a, b) => a.date.getTime() - b.date.getTime()).slice(0, MAX_REMINDERS);
}
