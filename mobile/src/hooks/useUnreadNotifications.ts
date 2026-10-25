import { useSyncExternalStore } from 'react';
import { fetchUnreadCount } from '@/api/endpoint/notifications.api';

let count = 0;
const listeners = new Set<() => void>();

function set(next: number): void {
    if (next !== count) {
        count = next;
        listeners.forEach(l => l());
    }
}

/** Asks the backend how many inbox items are unread. Offline or failing: keeps the last number. */
export async function refreshUnreadCount(): Promise<void> {
    try {
        set(await fetchUnreadCount());
    } catch {
        // keep the previous value
    }
}

export function setUnreadCount(next: number): void {
    set(Math.max(0, next));
}

export function useUnreadNotifications(): number {
    return useSyncExternalStore(
        l => {
            listeners.add(l);
            return () => {
                listeners.delete(l);
            };
        },
        () => count,
        () => count,
    );
}
