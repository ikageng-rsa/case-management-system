import { apiClient } from '@/api/client';
import { AppNotification, NotificationPreferences } from '@/models/index';
import { CacheNames } from '@/offline/cache';
import { cachedList } from '@/offline/repository';

/** The inbox (newest first). Readable offline from the last copy. */
export async function fetchNotifications(): Promise<AppNotification[]> {
  return cachedList<AppNotification>({
    name: CacheNames.notifications,
    request: async () => (await apiClient.get<AppNotification[]>('/notifications', { params: { limit: 100 } })).data,
  });
}

export async function fetchUnreadCount(): Promise<number> {
  const { data } = await apiClient.get<{ count: number }>('/notifications/unread-count');
  return data.count;
}

export async function markNotificationRead(id: string): Promise<void> {
  await apiClient.post(`/notifications/${id}/read`);
}

export async function markAllNotificationsRead(): Promise<void> {
  await apiClient.post('/notifications/read-all');
}

export async function fetchNotificationPreferences(): Promise<NotificationPreferences> {
  const { data } = await apiClient.get<NotificationPreferences>('/notifications/preferences');
  return data;
}

export async function updateNotificationPreferences(prefs: NotificationPreferences): Promise<NotificationPreferences> {
  const { data } = await apiClient.put<NotificationPreferences>('/notifications/preferences', prefs);
  return data;
}
