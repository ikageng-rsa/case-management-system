import { Href } from 'expo-router';

const SAFE_ID = /^[A-Za-z0-9_-]{1,64}$/;

/** Ids from a notification payload end up in a route, so accept only plain ids (uuid, numeric, tmp_…). */
export function safeId(value: unknown): string | null {
    return typeof value === 'string' && SAFE_ID.test(value) ? value : null;
}

/** Where tapping a notification should go. Anything unexpected falls back to the inbox. */
export function routeForNotification(data: unknown): Href {
    const payload = (typeof data === 'object' && data !== null ? data : {}) as Record<string, unknown>;
    const darzationId = safeId(payload.darzationId);
    if (darzationId) {
        return { pathname: '/diary/[darzationId]', params: { darzationId } };
    }
    const caseId = safeId(payload.caseId);
    if (caseId) {
        return { pathname: '/cases/[caseId]', params: { caseId } };
    }
    return '/more/notifications';
}
