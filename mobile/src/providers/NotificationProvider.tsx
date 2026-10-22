import React, { PropsWithChildren, useEffect } from 'react';
import * as Notifications from 'expo-notifications';
import { useRouter } from 'expo-router';
import { configureNotificationHandler, setupAndroidChannel } from '@/services/notifications/localNotifications';
import { useOverdueNotifications } from '@/hooks/useOverdueNotifications';

/**
 * Wires up the notification channel/handler once at app start and runs the
 * overdue-diary poll (popup + SMS) in the background for as long as the app
 * is mounted. Pure side-effect provider — renders nothing of its own.
 */
export default function NotificationProvider({ children }: PropsWithChildren) {
    useEffect(() => {
        configureNotificationHandler();
        setupAndroidChannel().catch(() => { });
    }, []);

    useOverdueNotifications();

    // Tapping a popup deep-links straight to the overdue diary item it is about.
    const router = useRouter();
    useEffect(() => {
        const sub = Notifications.addNotificationResponseReceivedListener(response => {
            const darzationId = response.notification.request.content.data?.darzationId;
            if (typeof darzationId === 'string') {
                router.push({ pathname: './diary/[darzationId]', params: { darzationId } });
            }
        });
        return () => sub.remove();
    }, [router]);

    return <>{children}</>;
}
