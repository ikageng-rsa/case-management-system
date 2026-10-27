import { useEffect, useState } from 'react';
import type { NotificationResponse } from 'expo-notifications';
import { getNotifications } from '@/services/notifications/notificationsModule';

/**
 * The notification the user last tapped, including the one that launched the app from a closed state.
 * Equivalent to expo-notifications' useLastNotificationResponse, but safe where the module is unavailable
 * (web, or a binary without the native module): it then simply never reports a tap.
 */
export function useNotificationTap(): NotificationResponse | null {
    const [response, setResponse] = useState<NotificationResponse | null>(null);

    useEffect(() => {
        const Notifications = getNotifications();
        if (!Notifications) {
            return;
        }
        let active = true;
        Notifications.getLastNotificationResponseAsync()
            .then(last => {
                if (active && last) {
                    setResponse(last);
                }
            })
            .catch(() => { });
        const sub = Notifications.addNotificationResponseReceivedListener(next => setResponse(next));
        return () => {
            active = false;
            sub.remove();
        };
    }, []);

    return response;
}
