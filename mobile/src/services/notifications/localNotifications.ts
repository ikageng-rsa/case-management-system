/**
 * Device-side display of notifications via expo-notifications. Alerts that come from the backend arrive as push
 * messages the OS shows itself; this module only configures how they look while the app is open, asks for
 * permission, and schedules the offline-safe local diary reminders (see features/darzations/services).
 */
import { Platform } from 'react-native';
import { getNotifications } from './notificationsModule';

/** The backend must set this as the Android channel id on diary pushes (FCM `android.notification.channel_id`). */
export const DIARY_CHANNEL_ID = 'diary-alerts';

let handlerConfigured = false;

/** Call once near app start (see @providers/NotificationProvider.tsx). */
export function configureNotificationHandler(): void {
    if (handlerConfigured) {
        return;
    }
    const Notifications = getNotifications();
    if (!Notifications) {
        return;
    }
    handlerConfigured = true;
    Notifications.setNotificationHandler({
        handleNotification: () => Promise.resolve ({
            shouldShowAlert: true,
            shouldShowBanner: true,
            shouldShowList: true,
            shouldPlaySound: false,
            shouldSetBadge: false,
        }),
    });
}

export async function ensureNotificationPermission(): Promise<boolean> {
    const Notifications = getNotifications();
    if (!Notifications) {
        return false;
    }
    const current = await Notifications.getPermissionsAsync();
    if (current.granted) {
        return true;
    }
    if (!current.canAskAgain) {
        return false;
    }
    const requested = await Notifications.requestPermissionsAsync();
    return requested.granted;
}

export async function setupAndroidChannel(): Promise<void> {
    const Notifications = getNotifications();
    if (Platform.OS !== 'android' || !Notifications) {
        return;
    }
    await Notifications.setNotificationChannelAsync(DIARY_CHANNEL_ID, {
        name: 'Diary alerts',
        importance: Notifications.AndroidImportance.HIGH,
        vibrationPattern: [0, 250, 250, 250],
        // Alerts name clients and matters: keep them off the lock screen until the phone is unlocked.
        lockscreenVisibility: Notifications.AndroidNotificationVisibility.PRIVATE,
    });
}

export interface ScheduledReminder {
    identifier: string;
    title: string;
    body: string;
    date: Date;
    data: Record<string, unknown>;
}

export async function scheduleReminder(reminder: ScheduledReminder): Promise<void> {
    const Notifications = getNotifications();
    if (!Notifications) {
        return;
    }
    await Notifications.scheduleNotificationAsync({
        identifier: reminder.identifier,
        content: {
            title: reminder.title,
            body: reminder.body,
            data: reminder.data,
            ...(Platform.OS === 'android' ? { channelId: DIARY_CHANNEL_ID } : {}),
        },
        trigger: { type: Notifications.SchedulableTriggerInputTypes.DATE, date: reminder.date },
    });
}

/** Cancels every scheduled reminder whose identifier starts with `prefix`. */
export async function cancelScheduledWithPrefix(prefix: string): Promise<void> {
    const Notifications = getNotifications();
    if (!Notifications) {
        return;
    }
    const all = await Notifications.getAllScheduledNotificationsAsync();
    await Promise.all(
        all.filter(n => n.identifier.startsWith(prefix)).map(n => Notifications.cancelScheduledNotificationAsync(n.identifier)),
    );
}
