/**
 * Offline-safe local reminders for diary(Darzation) items.
 * The backend is the source of truth for diary alerts: its scheduler decides who is told what , and delivers it
 * as push, SMS and email according to each user's preference. These local reminders exist only as a fallback
 * for a phone that is not registered for push (permission refused, no Google/Apple service, push not set up yet)
 * : They are scheduled on the device from the cached diary, so they fire even with no signal. As soon as the phone
 * is registered for push they are removed, so nobody is alerted twice.
 */
import { Darzation } from '@/models/index';
import { cancelScheduledWithPrefix, ensureNotificationPermission, scheduleReminder } from '@/services/notifications/localNotifications';
import { isPushRegistered } from '@/services/notifications/pushRegistration';
import { planReminders, REMINDER_PREFIX } from './reminderPlanner';


export async function cancelDiaryReminders(): Promise<void> {
  return cancelScheduledWithPrefix(REMINDER_PREFIX);
}

//Repleaces all scheduled diary reminders with thr right set for 'items'. Safe to call as often as the diary changes
export async function syncDiaryReminders(items: Darzation[], now: Date = new Date()): Promise<number> {
  await cancelDiaryReminders();
  if ((await isPushRegistered()) || !(await ensureNotificationPermission())) {
    return 0;
  }

  const plans = planReminders(items, now);
  for (const plan of plans) {
    void scheduleReminder(plan).catch(error => {console.error('failed to schedule a reminder: ',error)});
  }

  return plans.length;
}