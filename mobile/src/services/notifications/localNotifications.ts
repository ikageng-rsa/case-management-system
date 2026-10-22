/**
 * In-app / on-device popup notifications via expo-notifications — a free,
 * Expo-maintained module that needs no paid service or external account for
 * local (non-push) notifications, which is all overdue alerts need.
 */
import { Platform } from 'react-native';
import * as Notifications from 'expo-notifications';

let handlerConfigured = false;

/** Call once near app start (see @providers/NotificationProvider.tsx). */
export function configureNotificationHandler(): void {
    if (handlerConfigured) {
        return;
    }
    handlerConfigured = true;
    Notifications.setNotificationHandler({
        handleNotification: async () => ({
            shouldShowAlert: true,
            shouldShowBanner: true,
            shouldShowList: true,
            shouldPlaySound: true,
            shouldSetBadge: false,
        }),
    });
}

export async function ensureNotificationPermission(): Promise<boolean> {
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
    if (Platform.OS !== 'android') {
        return;
    }
    await Notifications.setNotificationChannelAsync('overdue-alerts', {
        name: 'Overdue diary alerts',
        importance: Notifications.AndroidImportance.HIGH,
        vibrationPattern: [0, 250, 250, 250],
    });
}

export interface PopupNotificationInput {
    title: string;
    body: string;
    data?: Record<string, unknown>;
}

/** Fires an immediate local popup/banner notification. */
export async function showPopupNotification({ title, body, data }: PopupNotificationInput): Promise<void> {
    const granted = await ensureNotificationPermission();
    if (!granted) {
        return;
    }
    await Notifications.scheduleNotificationAsync({
        content: { title, body, data, sound: true },
        trigger: null, // null trigger = fire immediately
    });
}

/** Schedules a popup for a future date (e.g. the moment a diary item becomes due). */
export async function scheduleFutureNotification(
    { title, body, data }: PopupNotificationInput,
    date: Date,
): Promise<string | null> {
    const granted = await ensureNotificationPermission();
    if (!granted || date.getTime() <= Date.now()) {
        return null;
    }
    return Notifications.scheduleNotificationAsync({
        content: { title, body, data, sound: true },
        trigger: { type: Notifications.SchedulableTriggerInputTypes.DATE, date },
    });
}

export async function cancelScheduledNotification(id: string): Promise<void> {
    await Notifications.cancelScheduledNotificationAsync(id).catch(() => { });
}
