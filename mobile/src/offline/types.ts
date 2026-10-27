export type SyncEntity = 'case' | 'darzation' | 'narration';

/** Marks a record that only exists on this device (or has local edits) until the sync engine pushes it. */
export type SyncState = 'pending' | 'failed';

export type MutationPatch =
    | { kind: 'merge'; fields: Record<string, unknown> }
    | { kind: 'step'; stepId: string; done: boolean };

/**
 * One queued change — the "collection" of offline work. Each entry is a replayable
 * HTTP request plus enough local information to show the change in the UI straight away.
 */
export interface OutboxEntry {
    /** Doubles as the Idempotency-Key header, so a retry after a lost response can't create a duplicate. */
    id: string;
    entity: SyncEntity;
    op: 'create' | 'update';
    /** Human label for the Sync screen, e.g. "New case — Mokoena v Road Accident Fund". */
    label: string;
    method: 'POST' | 'PATCH';
    url: string;
    body: unknown;
    /** Temp id (tmp_…) for a create; the id of the edited record for an update. */
    entityId: string;
    /** Local copy of a create, shown in lists until the server returns the real record. */
    optimistic?: Record<string, unknown>;
    patch?: MutationPatch;
    createdAt: string;
    attempts: number;
    /**
     * pending — waiting to be sent
     * failed  — the server rejected it (validation/permission); needs the user to retry or discard
     * blocked — depends on an earlier entry that failed
     */
    status: 'pending' | 'failed' | 'blocked';
    error?: string;
}

export interface SyncSnapshot {
    pending: number;
    failed: number;
    blocked: number;
    syncing: boolean;
    /** The saved login is no longer accepted by the server — the user must sign in online again. */
    authRequired: boolean;
    lastSyncAt: string | null;
    lastError: string | null;
}

export interface CacheMeta {
    lastSyncAt?: string;
    lastPrefetchAt?: string;
}
