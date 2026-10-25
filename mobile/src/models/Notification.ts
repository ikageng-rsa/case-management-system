export type NotificationType = 'diary_overdue' | 'diary_due_soon' | 'diary_assigned' | 'case_assigned';
export type NotificationChannelKey = 'push' | 'sms' | 'email';

/**
 * An item in the user's inbox. Notifications are created and delivered by the BACKEND (push / SMS / e-mail,
 * according to the user's preferences); the app only displays the inbox and marks items as read.
 */
export interface AppNotification {
    id: string;
    type: NotificationType;
    title: string;
    body: string;
    data: { caseId?: string; darzationId?: string };
    createdAt: string;
    readAt: string | null;
}

export type NotificationChannelPrefs = Record<NotificationChannelKey, boolean>;

export interface NotificationPreferences {
    types: Record<NotificationType, NotificationChannelPrefs>;
    /** The backend sends no SMS/push between these times (SAST); the inbox item is still created. */
    quietHours: { enabled: boolean; start: string; end: string };
}
