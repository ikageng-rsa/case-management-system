import React, { PropsWithChildren, useEffect, useRef } from 'react';
import { AppState } from 'react-native';
import { useRouter } from 'expo-router';
import { markNotificationRead } from '@/api/endpoint/notifications.api';
import { useAppSelector } from '@/hooks/useAppDispatch';
import { useDiaryReminders } from '@/hooks/useDiaryReminders';
import { refreshUnreadCount, setUnreadCount } from '@/hooks/useUnreadNotifications';
import { configureNotificationHandler, setupAndroidChannel } from '@/services/notifications/localNotifications';
import { getNotifications } from '@/services/notifications/notificationsModule';
import { registerPushDevice } from '@/services/notifications/pushRegistration';
import { useNotificationTap } from '@/hooks/useNotificationTap';
import { routeForNotification, safeId } from '@/utils/deepLinks';

/**
 * Notifications are produced and delivered by the backend. This provider only:
 *  - registers the phone's push token with the backend after sign-in (and when the OS rotates it),
 *  - keeps the unread badge current,
 *  - opens the right screen when a notification is tapped (validated, see utils/deepLinks),
 *  - runs the offline fallback reminders for phones that are not registered for push.
 */
export default function NotificationProvider({ children }: PropsWithChildren) {
    const router = useRouter();
    const userId = useAppSelector(state => state.auth.user?.id ?? null);
    const handled = useRef<string | null>(null);

    useEffect(() => {
        configureNotificationHandler();
        setupAndroidChannel().catch(() => { });
    }, []);

    useEffect(() => {
        if (!userId) {
            setUnreadCount(0);
            return;
        }
        registerPushDevice().catch((error) => {console.error('Failed to register Device:',error)});
        refreshUnreadCount().catch((error) => {console.error('Failed to refresh the count:',error)});

        const Notifications = getNotifications();
        const tokenSub = Notifications?.addPushTokenListener(() => {
            registerPushDevice().catch((error) => {console.error('Failed to register Device:',error)});
        });
        const receivedSub = Notifications?.addNotificationReceivedListener(() => {
            refreshUnreadCount().catch((error) => {console.error('Failed to refresh the count:',error)});
        });
        const appSub = AppState.addEventListener('change', state => {
            if (state === 'active') {
                refreshUnreadCount().catch((error) => {console.error('Failed to refresh the count:',error)});
            }
        });
        return () => {
            tokenSub?.remove();
            receivedSub?.remove();
            appSub.remove();
        };
    }, [userId]);

    // Tapping a notification (also the one that cold-started the app) opens what it is about.
    const lastResponse = useNotificationTap();
    useEffect(() => {
        if (!userId || !lastResponse) {
            return;
        }
        const id = lastResponse.notification.request.identifier;
        if (handled.current === id) {
            return;
        }
        handled.current = id;
        const data = lastResponse.notification.request.content.data;
        const notificationId = safeId(data?.notificationId);
        if (notificationId) {
            markNotificationRead(notificationId).then(refreshUnreadCount).catch(() => { });
        }
        router.push(routeForNotification(data));
    }, [lastResponse, userId, router]);

    useDiaryReminders();

    return <>{children}</>;
}
