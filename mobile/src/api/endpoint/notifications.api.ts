import { apiClient } from '@/api/client';
import { Document, Resource } from '@/api/jsonapi';
import { mapNotification } from '@/api/mappers/notification';
import { fetchAllPage } from '@/api/pagination';
import { AppNotification, NotificationPreferences } from '@/models/index';
import { CacheNames } from '@/offline/cache';
import { cachedList } from '@/offline/repository';

const INBOX_PAGES = 4; // 25 a page: the newest 100 is plenty for a phone inbox

/** The inbox, newest first. Readable offline from the last copy. */
export async function fetchNotifications(): Promise<AppNotification[]> {
  return cachedList<AppNotification>({
    name: CacheNames.notifications,
    request: async () => {
      const { data } = await fetchAllPage('/notifications', {}, INBOX_PAGES);
      return data.map(mapNotification);
    },
  });
}

/** The badge number: `meta.unread` on the first page of the unread list. */
export async function fetchUnreadCount(): Promise<number> {
  const { data: doc } = await apiClient.get<Document<Resource[]>>('/notifications', { params: { unread: 1 } });
  return Number(doc.meta?.unread ?? doc.meta?.total ?? doc.data?.length ?? 0);
}

export async function markNotificationRead(id: string): Promise<void> {
  await apiClient.post(`/notifications/${id}/read`);
}

export async function markAllNotificationsRead(): Promise<void> {
  await apiClient.post('/notifications/read-all');
}

/* Not in the v1 guide: only used when the app is built with EXPO_PUBLIC_API_FEATURES=notificationPreferences. */
export async function fetchNotificationPreferences(): Promise<NotificationPreferences> {
  const { data } = await apiClient.get<NotificationPreferences>('/notifications/preferences');
  return data;
}

export async function updateNotificationPreferences(prefs: NotificationPreferences): Promise<NotificationPreferences> {
  const { data } = await apiClient.put<NotificationPreferences>('/notifications/preferences', prefs);
  return data;
}
