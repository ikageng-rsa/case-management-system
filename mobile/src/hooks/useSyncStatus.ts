import { useEffect, useRef, useSyncExternalStore } from 'react';
import { getSyncSnapshot, subscribeSyncStatus, subscribeSynced } from '@/offline/syncStatus';
import { SyncSnapshot } from '@/offline/types';

/** Live counts of changes waiting to sync, whether a sync is running, and the last sync time. */
export function useSyncStatus(): SyncSnapshot {
    return useSyncExternalStore(subscribeSyncStatus, getSyncSnapshot, getSyncSnapshot);
}

/** Runs `reload` whenever queued changes have just reached the server, so lists show the real records. */
export function useOnSynced(reload: () => void): void {
    const latest = useRef(reload);
    useEffect(() => {
        latest.current = reload;
    });
    useEffect(() => subscribeSynced(() => latest.current()), []);
}
