
export type NotificationType = 'diary_overdue' | 'diary_due_soon' | 'diary_assigned' | 'case_Assigned';
export type NotificationChannelKey = 'push' | 'sms' | 'email';
/** A logged record of a notification the app sent (or attempted to send) to the current device/user. */
export interface AppNotification {
    id: string;
    title: NotificationType;
    body: string;
    data: {caseId?:string; daraztionId? : string};
    createdAt: string;
    readAt: string | null

}

export type NotificationChannelPrefs = Record<NotificationChannelKey, boolean>;

export interface NotificationPreferences{
    types: Record<NotificationType,NotificationChannelKey>;
    //The backend sends no SMS/push between thsese times(SAST); the inbox item is still created
    quietHours: {enabled: boolean, start: string; end: string;}
}