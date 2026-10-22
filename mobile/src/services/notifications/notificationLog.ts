
/**
 * On-device log of notifications the app has sent/attempted — backs the "Notifications"
 * screen under More so a user can see what fired even if they dismissed the popup/SMS.
 */
import AsyncStorage from '@react-native-async-storage/async-storage';
import { AppNotification } from '@/models/index';

const LOG_KEY = 'legalcms_notification_log';
const MAX_ENTRIES = 200;

export async function getNotificationLog(): Promise<AppNotification[]> {
    const raw = await AsyncStorage.getItem(LOG_KEY);
    return raw ? (JSON.parse(raw) as AppNotification[]) : [];
}

export async function logNotification(entry: Omit<AppNotification, 'id' | 'createdAt' | 'read'>): Promise<void> {
    const existing = await getNotificationLog();
    const next: AppNotification = {
        ...entry,
        id: `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
        createdAt: new Date().toISOString(),
        read: false,
    };
    const updated = [next, ...existing].slice(0, MAX_ENTRIES);
    await AsyncStorage.setItem(LOG_KEY, JSON.stringify(updated));
}

export async function markAllNotificationsRead(): Promise<void> {
    const existing = await getNotificationLog();
    await AsyncStorage.setItem(LOG_KEY, JSON.stringify(existing.map(n => ({ ...n, read: true }))));
}
