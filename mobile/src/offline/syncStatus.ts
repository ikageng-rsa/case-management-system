import { OutboxEntry, SyncSnapshot } from './types';

/**
 * Tiny observable store that the UI reads (banner, Sync screen, badges). Lives outside React so the
 * sync engine can update it from anywhere; hooks/useSyncStatus.ts subscribes to it.
 */
const initial: SyncSnapshot = {
    pending: 0,
    failed: 0,
    blocked: 0,
    syncing: false,
    authRequired: false,
    lastSyncAt: null,
    lastError: null,
};

let snapshot: SyncSnapshot = initial;
const listeners = new Set<() => void>();
const syncedListeners = new Set<() => void>();

export function getSyncSnapshot(): SyncSnapshot {
    return snapshot;
}

export function subscribeSyncStatus(listener: () => void): () => void {
    listeners.add(listener);
    return () => {
        listeners.delete(listener);
    };
}

export function setSyncStatus(patch: Partial<SyncSnapshot>): void {
    const next = { ...snapshot, ...patch };
    if ((Object.keys(next) as (keyof SyncSnapshot)[]).every(k => next[k] === snapshot[k])) {
        return;
    }
    snapshot = next;
    listeners.forEach(l => l());
}

export function setCountsFromOutbox(entries: OutboxEntry[]): void {
    setSyncStatus({
        pending: entries.filter(e => e.status === 'pending').length,
        failed: entries.filter(e => e.status === 'failed').length,
        blocked: entries.filter(e => e.status === 'blocked').length,
    });
}

export function resetSyncStatus(): void {
    snapshot = initial;
    listeners.forEach(l => l());
}

/** Fired after queued changes reached the server, so screens can reload what they show. */
export function subscribeSynced(listener: () => void): () => void {
    syncedListeners.add(listener);
    return () => {
        syncedListeners.delete(listener);
    };
}

export function emitSynced(): void {
    syncedListeners.forEach(l => l());
}

/* Cache-write guard: a list fetched before a sync finished must not overwrite what the sync wrote. */
let epoch = 0;
export const getSyncEpoch = (): number => epoch;
export const bumpSyncEpoch = (): void => {
    epoch += 1;
};
