import { Resource } from '@/api/jsonapi';
import { AppNotification, NotificationType } from '@/models/index';

const TITLES: Record<NotificationType, string> = {
    matter_assigned: 'Added to a matter',
    matter_unassigned: 'Removed from a matter',
    diary_assigned: 'Diary entry assigned to you',
    diary_due_soon: 'Diary entry due soon',
    diary_overdue: 'Diary entry overdue',
};

/**
 * One notification → the app's inbox item. The backend sends a finished sentence in `message`; the short title is
 * chosen here from `kind`. `matter_id` is null when there is nothing to open (e.g. taken off a matter), and
 * `diary_entry_id` is null unless the alert is about a diary entry.
 */
export function mapNotification(resource: Resource): AppNotification {
    const a = resource.attributes ?? {};
    const kind = String(a.kind ?? '') as NotificationType;
    return {
        id: String(resource.id),
        type: kind,
        title: TITLES[kind] ?? 'Notification',
        body: a.message ?? '',
        data: {
            caseId: a.matter_id ? String(a.matter_id) : undefined,
            darzationId: a.diary_entry_id ? String(a.diary_entry_id) : undefined,
        },
        createdAt: a.created_at ?? new Date().toISOString(),
        readAt: a.read_at ?? null,
    };
}
