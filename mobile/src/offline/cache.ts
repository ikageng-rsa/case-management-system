import AsyncStorage from '@react-native-async-storage/async-storage';
import { encGetItem, encSetItem } from '@/utils/encryptedStorage';
import { getActiveUserId } from './context';
import { cacheKey, listUserKeys, metaKey, outboxKey, readJson, serial, userPrefix, writeJson } from './storage';
import { CacheMeta, MutationPatch, OutboxEntry, SyncEntity } from './types';

export const CacheNames = {
    cases: 'cases',
    diary: 'diary',
    users: 'users',
    notifications: 'notifications',
    narrations: (caseId: string) => `narrations:${caseId}`,
    documents: (caseId: string) => `documents:${caseId}`,
};

type Row = { id: string } & Record<string, any>;

/* ---------------------------------------------------------------------------
 * Plain read/write of one cached collection (the active user's).
 * ------------------------------------------------------------------------- */

export async function cacheGet<T>(name: string, userId = getActiveUserId()): Promise<T | null> {
    return userId ? readJson<T>(cacheKey(userId, name)) : null;
}

/** Applies a queued edit to a cached record so the UI shows it before the server has confirmed it. */
export function applyPatch<T extends Row>(record: T, patch: MutationPatch): T {
    if (patch.kind === 'step') {
        const steps = Array.isArray(record.steps) ? record.steps : [];
        return { ...record, steps: steps.map((s: any) => (s.id === patch.stepId ? { ...s, done: patch.done } : s)), syncState: 'pending' };
    }
    return { ...record, ...patch.fields, syncState: 'pending' };
}

/** Adds still-queued creates and edits on top of a list fresh from the server (or the cache). */
export function overlayOutbox<T extends Row>(
    entity: SyncEntity,
    list: T[],
    outbox: OutboxEntry[],
    inScope: (row: Row) => boolean = () => true,
): T[] {
    let result = list;
    for (const entry of outbox) {
        if (entry.entity !== entity) {
            continue;
        }
        const state = entry.status === 'pending' ? 'pending' : 'failed';
        if (entry.op === 'create' && entry.optimistic) {
            const row = entry.optimistic as Row;
            if (inScope(row) && !result.some(r => r.id === entry.entityId)) {
                result = [{ ...row, syncState: state } as unknown as T, ...result];
            }
        } else if (entry.op === 'update' && entry.patch) {
            result = result.map(r => (r.id === entry.entityId ? { ...applyPatch(r, entry.patch!), syncState: state } : r));
        }
    }
    return result;
}

/* ---------------------------------------------------------------------------
 * Serialised mutations. Functions named *Locked must only run inside serial().
 * ------------------------------------------------------------------------- */

async function readOutbox(userId: string): Promise<OutboxEntry[]> {
    return (await readJson<OutboxEntry[]>(outboxKey(userId))) ?? [];
}

/**
 * Replaces the cached collection with a server copy — unless a sync finished meanwhile (the copy may be stale).
 * Returns the list with still-queued changes layered on top.
 */
export function writeListFromServer<T extends Row>(
    name: string,
    entity: SyncEntity | null,
    serverList: T[],
    inScope: (row: Row) => boolean,
    epochAtRequest: number,
    currentEpoch: () => number,
): Promise<T[]> {
    const userId = getActiveUserId();
    if (!userId) {
        return Promise.resolve(serverList);
    }
    return serial(async () => {
        const outbox = entity ? await readOutbox(userId) : [];
        const merged = entity ? overlayOutbox(entity, serverList, outbox, inScope) : serverList;
        if (epochAtRequest === currentEpoch()) {
            // Only the server's copy is cached; queued changes are layered on top when reading,
            // so discarding a queued change leaves nothing behind in the cache.
            await writeJson(cacheKey(userId, name), serverList);
        }
        return merged;
    });
}

/** Insert or replace one record in a cached collection (new records go to the top). Must run inside serial(). */
export async function cacheUpsertLocked<T extends Row>(userId: string, name: string, record: T): Promise<void> {
    const list = (await readJson<T[]>(cacheKey(userId, name))) ?? [];
    const idx = list.findIndex(r => r.id === record.id);
    const next = idx >= 0 ? list.map((r, i) => (i === idx ? record : r)) : [record, ...list];
    await writeJson(cacheKey(userId, name), next);
}

export function cacheUpsert<T extends Row>(name: string, record: T): Promise<void> {
    const userId = getActiveUserId();
    return userId ? serial(() => cacheUpsertLocked(userId, name, record)) : Promise.resolve();
}

export function cacheSet(name: string, value: unknown): Promise<void> {
    const userId = getActiveUserId();
    return userId ? serial(() => writeJson(cacheKey(userId, name), value)) : Promise.resolve();
}

/**
 * After the server has accepted a queued create, swap the temporary id for the real one
 * everywhere it is mentioned: cached records, cache keys (e.g. narrations:tmp_… ) and
 * the remaining queued requests (e.g. a narration waiting on a case created offline).
 * Temp ids are unique 16-hex tokens, so a plain text replace is safe.
 */
export async function remapTempIdLocked(userId: string, tempId: string, realId: string): Promise<void> {
    for (const key of await listUserKeys(userId)) {
        if (key === metaKey(userId)) {
            continue;
        }
        const raw = await encGetItem(key);
        if (raw === null) {
            continue;
        }
        const nextRaw = raw.split(tempId).join(realId);
        const nextKey = key.split(tempId).join(realId);
        if (nextKey !== key) {
            await AsyncStorage.removeItem(key);
        }
        if (nextKey !== key || nextRaw !== raw) {
            await encSetItem(nextKey, nextRaw);
        }
    }
}

/** Runs `task` as one uninterrupted read-modify-write step over cache + outbox. */
export const exclusive = serial;

export async function cacheMeta(userId = getActiveUserId()): Promise<CacheMeta> {
    return userId ? (await readJson<CacheMeta>(metaKey(userId))) ?? {} : {};
}

export function updateCacheMeta(patch: CacheMeta): Promise<void> {
    const userId = getActiveUserId();
    if (!userId) {
        return Promise.resolve();
    }
    return serial(async () => {
        const current = (await readJson<CacheMeta>(metaKey(userId))) ?? {};
        await writeJson(metaKey(userId), { ...current, ...patch });
    });
}

/** Wipes everything stored for one user (cache, queue, meta). */
export async function clearUserData(userId: string): Promise<void> {
    const keys = await listUserKeys(userId);
    await AsyncStorage.multiRemove(keys);
}

export { userPrefix };
