/**
 * What may be done without a connection.
 *   - Adding cases, diary items and narrations: always allowed (queued, then synced).
 *   - Deleting anything: never allowed offline.
 *   - Editing records that already exist on the server (status changes, step ticks, case edits):
 *     blocked offline by default so two people can't silently overwrite each other's changes.
 *     Set EXPO_PUBLIC_OFFLINE_EDITS=true to queue those too (last write wins when they sync).
 */
export const OFFLINE_EDITS_ENABLED = process.env.EXPO_PUBLIC_OFFLINE_EDITS === 'true';

export class OfflineNotAllowedError extends Error {
    constructor(action: string) {
        super(`You're offline. ${action} needs an internet connection — you can still add new cases, diary items and narrations.`);
        this.name = 'OfflineNotAllowedError';
    }
}
