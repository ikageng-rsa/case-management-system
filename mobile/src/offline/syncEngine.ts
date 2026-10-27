import { apiClient } from '@/api/client';
import { getApiErrorMessage } from '@/api/errors';
import { isAxiosError } from 'axios';
import { cacheUpsertLocked, CacheNames, updateCacheMeta } from './cache';
import { getActiveUserId } from './context';
import { isDeviceOffline, isNetworkError } from './connectivity';
import { completeEntry, listOutbox, removeEntries, subscribeOutbox, updateEntry } from './outbox';
import { bumpSyncEpoch, emitSynced, setCountsFromOutbox, setSyncStatus } from './syncStatus';

const TEMP_TOKEN = /tmp_[a-f0-9]{16}/g;

/** Hooks that other features register so this module doesn't depend on them (e.g. narration documents). */
type EntryDoneHandler = (entry: OutboxEntry, serverRecord: Record<string, any>) => Promise<void>;
type EntryDiscardHandler = (entry: OutboxEntry) => Promise<void>;
const doneHandlers: EntryDoneHandler[] = [];
const discardHandlers: EntryDiscardHandler[] = [];
export const onEntrySynced = (h: EntryDoneHandler): void => { doneHandlers.push(h) };
export const onEntryDiscarded = (h: EntryDiscardHandler): void => { discardHandlers.push(h) };

const tempIdsIn = (entry: OutboxEntry): string[] =>
    [...new Set(`${entry.url} ${JSON.stringify(entry.body ?? '')}`.match(TEMP_TOKEN) ?? [])];

/** Other queued entries that mention this entry's temporary id — they can't be sent until it is. */
function dependentsOf(entry: OutboxEntry, all: OutboxEntry[]): OutboxEntry[] {
    return all.filter(e => e.id !== entry.id && tempIdsIn(e).includes(entry.entityId));
}

function collectDependents(root: OutboxEntry, all: OutboxEntry[]): OutboxEntry[] {
    const seen = new Map<string, OutboxEntry>();
    const walk = (e: OutboxEntry) => {
        for (const d of dependentsOf(e, all)) {
            if (!seen.has(d.id)) {
                seen.set(d.id, d);
                walk(d);
            }
        }
    };
    walk(root);
    return [...seen.values()];
}

function listFor(entity: OutboxEntry['entity'], record: Record<string, any>): string {
    if (entity === 'case') return CacheNames.cases;
    if (entity === 'darzation') return CacheNames.diary;
    return CacheNames.narrations(String(record.caseId));
}

export async function refreshOutboxCounts(): Promise<void> {
    setCountsFromOutbox(await listOutbox());
}

type Outcome = 'done' | 'failed' | 'offline' | 'auth' | 'retry-later';

async function sendEntry(entry: OutboxEntry): Promise<Outcome> {
    try {
        const { data } = await apiClient.request({
            method: entry.method,
            url: entry.url,
            data: entry.body,
            headers: { 'Idempotency-Key': entry.id },
        });
        await completeEntry(entry, entry.op === 'create' ? String(data.id) : null, async () => {
            // Runs inside completeEntry's exclusive step, so it must use the Locked variant (no nested lock).
            const userId = getActiveUserId();
            if (userId) {
                await cacheUpsertLocked(userId, listFor(entry.entity, data), data);
            }
            bumpSyncEpoch();
        });
        for (const handler of doneHandlers) {
            handler(entry, data).catch(() => { });
        }
        return 'done';
    } catch (error) {
        if (isNetworkError(error)) {
            await updateEntry(entry.id, { attempts: entry.attempts + 1 });
            return 'offline';
        }
        const status = isAxiosError(error) ? error.response?.status : undefined;
        if (status === 401) {
            return 'auth';
        }
        if (status !== undefined && (status >= 500 || status === 408 || status === 429)) {
            await updateEntry(entry.id, { attempts: entry.attempts + 1, error: getApiErrorMessage(error) });
            return 'retry-later';
        }
        // The server understood and refused (validation, permission, record gone): retrying can't help.
        await updateEntry(entry.id, {
            status: 'failed',
            attempts: entry.attempts + 1,
            error: status === 403 ? "You don't have permission to save this." : getApiErrorMessage(error, 'The server rejected this change.'),
        });
        return 'failed';
    }
}

let running: Promise<void> | null = null;

type OutboxEntries = Awaited<ReturnType<typeof listOutbox>>;
type OutboxEntry = OutboxEntries[number];
type SendOutcome = Awaited<ReturnType<typeof sendEntry>>;

async function hasDependencyProblem(
    all: OutboxEntries,
    next: OutboxEntry,
): Promise<boolean> {
    const dependencies = tempIdsIn(next);

    const missing = dependencies.some(
        id => !all.some(entry => entry.entityId === id),
    );

    if (missing) {
        await updateEntry(next.id, {
            status: 'failed',
            error: 'It depends on an item that was discarded or never saved.',
        });
        return true;
    }

    const blocker = all.find(
        entry =>
            entry.id !== next.id &&
            entry.status !== 'pending' &&
            dependencies.includes(entry.entityId),
    );

    if (blocker) {
        await updateEntry(next.id, {
            status: 'blocked',
            error: `Waiting for "${blocker.label}", which needs attention.`,
        });
        return true;
    }

    return false;
}


function shouldStopSync(outcome: SendOutcome): boolean {
    switch (outcome) {
        case 'auth':
            setSyncStatus({
                authRequired: true,
                lastError:
                    'Your saved sign-in has expired. Sign in again while online to sync.',
            });
            return true;

        case 'offline':
        case 'retry-later':
            setSyncStatus({
                lastError:
                    outcome === 'offline'
                        ? 'No connection — will retry.'
                        : 'The server is busy — will retry.',
            });
            return true;

        default:
            return false;
    }
}


/**
 * Sends everything in the outbox, oldest first, stopping as soon as the connection drops.
 * Safe to call from anywhere, any number of times: overlapping calls share one run.
 */
export function flushOutbox(): Promise<void> {
    if (running) {
        return running;
    }

    running = (async () => {
        if (!getActiveUserId() || (await isDeviceOffline())) {
            await refreshOutboxCounts();
            return;
        }

        setSyncStatus({ syncing: true, lastError: null });
        let completed = 0;

        try {
            while (true) {
                const all = await listOutbox();
                const next = all.find(entry => entry.status === 'pending');

                if (!next) {
                    break;
                }

                if (await hasDependencyProblem(all, next)) {
                    continue;
                }

                const outcome = await sendEntry(next);

                if (outcome === 'done') {
                    completed += 1;
                    continue;
                }

                if (shouldStopSync(outcome)) {
                    break;
                }
            }

            if (completed > 0) {
                const now = new Date().toISOString();

                await updateCacheMeta({ lastSyncAt: now });
                setSyncStatus({ lastSyncAt: now, authRequired: false });
            }
        } finally {
            await refreshOutboxCounts();
            setSyncStatus({ syncing: false });

            if (completed > 0) {
                emitSynced();
            }
        }
    })().finally(() => {
        running = null;
    });

    return running;
}

/** Puts a failed entry (and anything waiting on it) back in the queue and tries again. */
export async function retryEntry(id: string): Promise<void> {
    const all = await listOutbox();
    const entry = all.find(e => e.id === id);
    if (!entry) {
        return;
    }
    for (const e of [entry, ...collectDependents(entry, all)]) {
        void updateEntry(e.id, { status: 'pending', error: undefined }).catch((error) => {console.error('failed entry update: ',error)});
    }
    await flushOutbox();
}

/**
 * Throws away an unsent change. Discarding a create also discards what was built on it
 * (e.g. the narrations written against a case that was never saved).
 */
export async function discardEntry(id: string): Promise<void> {
    const all = await listOutbox();
    const entry = all.find(e => e.id === id);
    if (!entry) {
        return;
    }
    const doomed = [entry, ...(entry.op === 'create' ? collectDependents(entry, all) : [])];
    await removeEntries(doomed.map(e => e.id));
    for (const d of doomed) {
        for (const handler of discardHandlers) {
            handler(d).catch(() => { });
        }
    }
    await refreshOutboxCounts();
}

/** Starts keeping the status counters current. Call once at app start. */
export function startOutboxWatcher(): () => void {
    refreshOutboxCounts().catch(() => { });
    return subscribeOutbox(() => {
        refreshOutboxCounts().catch(() => { });
    });
}

export { containsTempId } from './ids';
