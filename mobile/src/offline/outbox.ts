import { getActiveUserId } from './context';
import { exclusive, remapTempIdLocked } from './cache';
import { outboxKey, readJson, writeJson } from './storage';
import { OutboxEntry } from './types';

type Listener = () => void;
const listeners = new Set<Listener>();

export function subscribeOutbox(listener: Listener): () => void {
    listeners.add(listener);
    return () => {
        listeners.delete(listener);
    };
}

function emit(): void {
    listeners.forEach(l => l());
}

async function load(userId: string): Promise<OutboxEntry[]> {
    return (await readJson<OutboxEntry[]>(outboxKey(userId))) ?? [];
}

/** Everything still waiting to reach the server, oldest first. */
export async function listOutbox(userId = getActiveUserId()): Promise<OutboxEntry[]> {
    return userId ? load(userId) : [];
}

export async function outboxSize(): Promise<number> {
    return (await listOutbox()).length;
}

export function enqueue(entry: OutboxEntry): Promise<void> {
    const userId = getActiveUserId();
    if (!userId) {
        return Promise.resolve();
    }
    return exclusive(async () => {
        await writeJson(outboxKey(userId), [...(await load(userId)), entry]);
    }).then(emit);
}

export function updateEntry(id: string, patch: Partial<OutboxEntry>): Promise<void> {
    const userId = getActiveUserId();
    if (!userId) {
        return Promise.resolve();
    }
    return exclusive(async () => {
        const list = await load(userId);
        await writeJson(outboxKey(userId), list.map(e => (e.id === id ? { ...e, ...patch } : e)));
    }).then(emit);
}

/**
 * Removes the entries with these ids. Used when the user discards a change; discarding a create
 * also discards everything that depended on it (see discardEntry in syncEngine).
 */
export function removeEntries(ids: string[]): Promise<void> {
    const userId = getActiveUserId();
    if (!userId) {
        return Promise.resolve();
    }
    return exclusive(async () => {
        const list = await load(userId);
        await writeJson(outboxKey(userId), list.filter(e => !ids.includes(e.id)));
    }).then(emit);
}

/**
 * One atomic step after a successful send: drop the entry, then (for creates) swap the temporary id
 * for the real one in the cache and the rest of the queue. `afterRemap` runs inside the same step so
 * the server's record can be written to the cache before anything else reads it.
 */
export function completeEntry(
    entry: OutboxEntry,
    realId: string | null,
    afterRemap?: () => Promise<void>,
): Promise<void> {
    const userId = getActiveUserId();
    if (!userId) {
        return Promise.resolve();
    }
    return exclusive(async () => {
        const list = await load(userId);
        await writeJson(outboxKey(userId), list.filter(e => e.id !== entry.id));
        if (realId && entry.op === 'create') {
            await remapTempIdLocked(userId, entry.entityId, realId);
        }
        if (afterRemap) {
            await afterRemap();
        }
    }).then(emit);
}
