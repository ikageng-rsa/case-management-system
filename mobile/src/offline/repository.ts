import { apiClient } from '@/api/client';
import { applyPatch, cacheGet, cacheUpsert, overlayOutbox, writeListFromServer } from './cache';
import { getActiveUserId } from './context';
import { isDeviceOffline, isNetworkError, isTransientError } from './connectivity';
import { containsTempId, isTempId, newRequestId, newTempId } from './ids';
import { enqueue, listOutbox } from './outbox';
import { OFFLINE_EDITS_ENABLED, OfflineNotAllowedError } from './policy';
import { getSyncEpoch } from './syncStatus';
import { MutationPatch, OutboxEntry, SyncEntity } from './types';

type Row = { id: string } & Record<string, any>;

/*  
 * Reads: network first, cache when there is none.
 * ------------------------------------------------------------------------- */

interface ListOptions<T> {
    /** Cache collection name (see CacheNames). */
    name: string;
    entity?: SyncEntity;
    request: () => Promise<T[]>;
    /** Which queued records belong in this list (e.g. narrations of one case). */
    inScope?: (row: Row) => boolean;
    /** False when the request is filtered/searched — such a result must not replace the full cached list. */
    cacheable?: boolean;
    /** Applied to the final list; also how a filter/search is honoured when answering from the cache. */
    filter?: (rows: T[]) => T[];
}

async function fromCache<T extends Row>(o: ListOptions<T>): Promise<T[]> {
    const cached = (await cacheGet<T[]>(o.name)) ?? [];
    const rows = o.entity ? overlayOutbox(o.entity, cached, await listOutbox(), o.inScope) : cached;
    return o.filter ? o.filter(rows) : rows;
}

export async function cachedList<T extends Row>(o: ListOptions<T>): Promise<T[]> {
    // A list that belongs to a record the server hasn't seen yet can only be answered locally.
    const localOnly = containsTempId(o.name);
    if (!localOnly && !(await isDeviceOffline())) {
        const epoch = getSyncEpoch();
        try {
            const server = await o.request();
            let rows: T[];
            if (o.cacheable === false) {
                rows = o.entity ? overlayOutbox(o.entity, server, await listOutbox(), o.inScope) : server;
            } else {
                rows = await writeListFromServer(o.name, o.entity ?? null, server, o.inScope ?? (() => true), epoch, getSyncEpoch);
            }
            return o.filter ? o.filter(rows) : rows;
        } catch (error) {
            if (!isTransientError(error)) {
                throw error;
            }
        }
    }
    return fromCache(o);
}

export async function cachedRecord<T extends Row>(o: {
    id: string;
    listName: string;
    entity: SyncEntity;
    request: () => Promise<T>;
    inScope?: (row: Row) => boolean;
    notFound?: string;
}): Promise<T> {
    const local = async (): Promise<T> => {
        const cached = (await cacheGet<T[]>(o.listName)) ?? [];
        const rows = overlayOutbox(o.entity, cached, await listOutbox(), o.inScope);
        const found = rows.find(r => r.id === o.id);
        if (!found) {
            throw new Error(o.notFound ?? "This item isn't saved on this device. Open it once while online to make it available offline.");
        }
        return found;
    };

    if (isTempId(o.id) || containsTempId(o.listName) || (await isDeviceOffline())) {
        return local();
    }
    try {
        const server = await o.request();
        await cacheUpsert(o.listName, server);
        return overlayOutbox(o.entity, [server], await listOutbox(), o.inScope)[0] ?? server;
    } catch (error) {
        if (!isTransientError(error)) {
            throw error;
        }
        return local();
    }
}

/*  
 * Writes
 * ------------------------------------------------------------------------- */

/**
 * Create a record. Online: a normal request. No connection (or the request never got an answer):
 * the request is parked in the outbox with a temporary id and the app carries on with the local copy.
 * The request id doubles as the Idempotency-Key, so if the answer was merely lost, replaying it later
 * can't create a duplicate.
 */
export async function submitCreate<T extends Row>(o: {
    entity: SyncEntity;
    label: string;
    url: string;
    body: Record<string, unknown>;
    /** Local copy shown until the server returns the real record. */
    optimistic: (tempId: string, createdAt: string) => T;
    /** Cache collections to add the server's record to once it exists. */
    listName: (created: T) => string;
}): Promise<T> {
    const requestId = newRequestId();
    const createdAt = new Date().toISOString();

    if (!containsTempId(o.url) && !containsTempId(o.body) && !(await isDeviceOffline())) {
        try {
            const { data } = await apiClient.post<T>(o.url, o.body, { headers: { 'Idempotency-Key': requestId } });
            await cacheUpsert(o.listName(data), data);
            return data;
        } catch (error) {
            if (!isNetworkError(error)) {
                throw error; // the server answered (validation, permission...) — the user needs to see that
            }
        }
    }

    if (!getActiveUserId()) {
        throw new Error('Sign in to save changes.');
    }
    const tempId = newTempId();
    const optimistic = { ...o.optimistic(tempId, createdAt), syncState: 'pending' as const };
    const entry: OutboxEntry = {
        id: requestId,
        entity: o.entity,
        op: 'create',
        label: o.label,
        method: 'POST',
        url: o.url,
        // Tells the server when the record was really made, so offline work keeps its true date.
        body: { ...o.body, clientCreatedAt: createdAt },
        entityId: tempId,
        optimistic,
        createdAt,
        attempts: 0,
        status: 'pending',
    };
    await enqueue(entry);
    return optimistic;
}

/**
 * Edit an existing record. Editing offline is off by default (see policy.ts): without a connection
 * this throws a friendly error instead of queueing, so two people can't silently overwrite each other.
 */
export async function submitUpdate<T extends Row>(o: {
    entity: SyncEntity;
    entityId: string;
    /** What the user was trying to do, for the error message and the Sync screen ("Edit case"). */
    action: string;
    label: string;
    url: string;
    body: Record<string, unknown>;
    patch: MutationPatch;
    listName: string;
}): Promise<T> {
    const target = isTempId(o.entityId) || containsTempId(o.url);
    if (!target && !(await isDeviceOffline())) {
        try {
            const { data } = await apiClient.patch<T>(o.url, o.body, { headers: { 'Idempotency-Key': newRequestId() } });
            await cacheUpsert(o.listName, data);
            return data;
        } catch (error) {
            if (!isNetworkError(error)) {
                throw error;
            }
        }
    }

    if (!OFFLINE_EDITS_ENABLED || !getActiveUserId()) {
        throw new OfflineNotAllowedError(o.action);
    }
    const cached = (await cacheGet<T[]>(o.listName)) ?? [];
    const current = overlayOutbox(o.entity, cached, await listOutbox()).find(r => r.id === o.entityId);
    if (!current) {
        throw new OfflineNotAllowedError(o.action);
    }
    const entry: OutboxEntry = {
        id: newRequestId(),
        entity: o.entity,
        op: 'update',
        label: o.label,
        method: 'PATCH',
        url: o.url,
        body: o.body,
        entityId: o.entityId,
        patch: o.patch,
        createdAt: new Date().toISOString(),
        attempts: 0,
        status: 'pending',
    };
    await enqueue(entry);
    return { ...applyPatch(current, o.patch), syncState: 'pending' } as T;
}
