export type NotificationChannel = 'push' | 'sms' | 'in-app';
export type NotificationReason = 'overdue-darzation' | 'reminder' | 'otp' | 'general';

/** A logged record of a notification the app sent (or attempted to send) to the current device/user. */
export interface AppNotification {
    id: string;
    title: string;
    body: string;
    channel: NotificationChannel;
    reason: NotificationReason;
    relatedCaseId?: string;
    relatedDarzationId?: string;
    createdAt: string;
    read: boolean;
}
