/**
 * Tells the BACKEND which phone to push to. The app never contacts a push/SMS/e-mail provider itself: it only
 * hands over its device token (FCM on Android, APNs on iOS) and the server decides what to send and when.
 */
import { Platform } from 'react-native';
import Constants from 'expo-constants';
import * as Notifications from 'expo-notifications';
import { registerDevice, unregisterDevice } from '@/api/endpoint/devices.api';
import { secureDelete, secureGet, secureSet } from '@/utils/secureStorage';
import { ensureNotificationPermission } from './localNotifications';

const DEVICE_ID_KEY = 'legalcms_push_device_id';

async function currentToken(): Promise<{ token: string; tokenType: 'fcm' | 'apns' | 'expo' } | null> {
    // 'native' (default): the raw FCM/APNs token, so the backend talks to Google/Apple directly.
    // 'expo': an Expo push token, if the backend is set up to relay through Expo's service.
    if (process.env.EXPO_PUBLIC_PUSH_TOKEN_TYPE === 'expo') {
        const projectId = Constants.expoConfig?.extra?.eas?.projectId ?? Constants.easConfig?.projectId;
        const expo = await Notifications.getExpoPushTokenAsync(projectId ? { projectId } : undefined);
        return { token: expo.data, tokenType: 'expo' };
    }
    const native = await Notifications.getDevicePushTokenAsync();
    if (typeof native.data !== 'string') {
        return null;
    }
    return { token: native.data, tokenType: native.type === 'ios' ? 'apns' : 'fcm' };
}

/** True once this phone is registered with the backend for push (local reminders then stand down). */
export async function isPushRegistered(): Promise<boolean> {
    return (await secureGet(DEVICE_ID_KEY)) !== null;
}

/** Call after sign-in and whenever the OS rotates the token. Never throws: push is an enhancement. */
export async function registerPushDevice(): Promise<boolean> {
    if (Platform.OS === 'web') {
        return false;
    }
    try {
        if (!(await ensureNotificationPermission())) {
            return false;
        }
        const info = await currentToken();
        if (!info) {
            return false;
        }
        const { id } = await registerDevice({
            ...info,
            platform: Platform.OS === 'ios' ? 'ios' : 'android',
            deviceName: `${Platform.OS} ${Platform.Version}`,
        });
        await secureSet(DEVICE_ID_KEY, id);
        return true;
    } catch {
        return false;
    }
}

/** Call at sign-out, before the session token is cleared, so the next person on this phone gets none of these alerts. */
export async function unregisterPushDevice(): Promise<void> {
    const id = await secureGet(DEVICE_ID_KEY);
    if (!id) {
        return;
    }
    try {
        await unregisterDevice(id);
    } finally {
        await secureDelete(DEVICE_ID_KEY);
    }
}
