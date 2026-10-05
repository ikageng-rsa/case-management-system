/**
 * Wraps Firebase Cloud Messaging (Android) local/scheduled notifications for
 * Darzation (diary) alerts — "overdue" and "daily digest" from the Sprint 9
 * backlog. Swap the body of these functions for @react-native-firebase/messaging
 * + notifee once the push infra is provisioned; kept as a typed stub so the
 * rest of the app can be built against a stable interface today.
 */
import { Darzation } from '@/models/index';

export  function scheduleOverdueAlert(darzation: Darzation): void {
  // TODO: integrate notifee.createTriggerNotification with darzation.nextActionDate
  console.log(`[stub] would schedule overdue alert for ${darzation.id} on ${darzation.nextActionDate}`);
}

export  function scheduleDailyDigest(items: Darzation[]): void {
  // TODO: integrate a daily repeating trigger summarising `items`
  console.log(`[stub] would schedule daily digest for ${items.length} diary item(s)`);
}

export  function cancelAlert(darzationId: string): void {
  console.log(`[stub] would cancel alert for ${darzationId}`);
}
