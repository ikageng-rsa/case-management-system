import { Platform } from 'react-native';

export type NotificationsModule = typeof import('expo-notifications');

let cached: NotificationsModule | null | undefined;

/**
 * expo-notifications, or null when it can't be used here: on the web, or where its native module isn't part of
 * the running binary (e.g. an Expo Go build without it). Importing it eagerly would crash the whole app at
 * start-up, so every caller goes through this and treats null as "notifications unavailable".
 */
export function getNotifications(): NotificationsModule | null {
    if (cached !== undefined) {
        return cached;
    }
    if (Platform.OS === 'web') {
        cached = null;
        return cached;
    }
    try {
        // eslint-disable-next-line @typescript-eslint/no-require-imports
        cached = require('expo-notifications') as NotificationsModule;
    } catch {
        cached = null;
    }
    return cached;
}
