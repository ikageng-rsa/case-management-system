import { useSyncExternalStore } from 'react';
import { fetchNotifications, fetchUnreadCount } from '@/api/endpoint/notifications.api';
import { getNotifications } from '@/services/notifications/notificationsModule';
import { AppNotification } from '@/models/index';

export interface UnreadCounts {
    /** Every unread inbox item (More tab, app icon). */
    total: number;
    /** Unread diary alerts (Diary tab). */
    diary: number;
    /** Unread case alerts (Cases tab). */
    cases: number;
}

const ZERO: UnreadCounts = { total: 0, diary: 0, cases: 0 };
let counts: UnreadCounts = ZERO;
const listeners = new Set<() => void>();

function set(next: UnreadCounts): void {
    if (next.total !== counts.total || next.diary !== counts.diary || next.cases !== counts.cases) {
        counts = next;
        listeners.forEach(l => l());
    }
}

/** Splits the unread items of an inbox into the tab each belongs to. */
export function countUnread(list: Pick<AppNotification, 'type' | 'readAt'>[], serverTotal?: number): UnreadCounts {
    const unread = list.filter(n => !n.readAt);
    return {
        total: Math.max(serverTotal ?? 0, unread.length),
        diary: unread.filter(n => n.type.startsWith('diary_')).length,
        cases: unread.filter(n => n.type === 'case_assigned').length,
    };
}

function updateAppIconBadge(total: number): void {
    // Optional: the number on the app icon. Silently skipped where notifications aren't available.
    getNotifications()?.setBadgeCountAsync(total).catch(() => { });
}

/** Asks the backend what is unread. Offline or failing: keeps the last numbers. */
export async function refreshUnreadCount(): Promise<void> {
    try {
        const [total, list] = await Promise.all([fetchUnreadCount(), fetchNotifications().catch(() => [] as AppNotification[])]);
        const next = countUnread(list, total);
        set(next);
        updateAppIconBadge(next.total);
    } catch {
        // keep the previous values
    }
}

export function setUnreadCount(next: number): void {
    set(next <= 0 ? ZERO : { ...counts, total: next });
    if (next <= 0) {
        updateAppIconBadge(0);
    }
}

function subscribe(listener: () => void): () => void {
    listeners.add(listener);
    return () => {
        listeners.delete(listener);
    };
}

/** Total unread (kept for existing callers). */
export function useUnreadNotifications(): number {
    return useSyncExternalStore(subscribe, () => counts.total, () => counts.total);
}

/** Unread numbers per tab. */
export function useUnreadCounts(): UnreadCounts {
    return useSyncExternalStore(subscribe, () => counts, () => counts);
}
