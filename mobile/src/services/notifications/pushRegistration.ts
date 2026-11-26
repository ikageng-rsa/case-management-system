/**
 * Tells the BACKEND which phone to push to. The app never contacts a push/SMS/e-mail provider itself: it only
 * hands over its device token (FCM on Android, APNs on iOS) and the server decides what to send and when.
 */
import { Platform } from 'react-native';
import { hasFeature } from '@/config/features';
import { MAX_PUSH_TOKEN_LENGTH, registerDevice, unregisterDevice } from '@/api/endpoint/devices.api';
import { secureDelete, secureGet, secureSet } from '@/utils/secureStorage';
import { ensureNotificationPermission } from './localNotifications';
import { getNotifications } from './notificationsModule';

const DEVICE_ID_KEY = 'legalcms_push_device_id';

/** The phone's own push token: FCM on Android, APNs on iOS. The v1 API stores one token per sign-in (max 512 chars). */
async function currentToken(): Promise<string | null> {
    const Notifications = getNotifications();
    if (!Notifications) {
        return null;
    }
    const native = await Notifications.getDevicePushTokenAsync();
    const token = native.data;
    return typeof token === 'string' && token.length > 0 && token.length <= MAX_PUSH_TOKEN_LENGTH ? token : null;
}

/** True once this phone is registered with the backend for push (local reminders then stand down). */
export async function isPushRegistered(): Promise<boolean> {
    return (await secureGet(DEVICE_ID_KEY)) !== null;
}

/** Call after sign-in and whenever the OS rotates the token. Never throws: push is an enhancement. */
export async function registerPushDevice(): Promise<boolean> {
    if (Platform.OS === 'web' || !hasFeature('notifications')) {
        return false;
    }
    try {
        if (!(await ensureNotificationPermission())) {
            return false;
        }
        const pushToken = await currentToken();
        if (!pushToken) {
            return false;
        }
        const { id } = await registerDevice({ pushToken, platform: Platform.OS === 'ios' ? 'ios' : 'android' });
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
