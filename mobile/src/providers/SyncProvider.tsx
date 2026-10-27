import React, { PropsWithChildren, useEffect } from 'react';
import { AppState } from 'react-native';
import NetInfo from '@react-native-community/netinfo';
import { useAppDispatch, useAppSelector } from '@/hooks/useAppDispatch';
import { loadCases } from '@/features/cases/store/casesSlice';
import { prefetchAll } from '@/offline/prefetch';
import { flushOutbox, startOutboxWatcher } from '@/offline/syncEngine';
import { getSyncSnapshot, subscribeSynced } from '@/offline/syncStatus';
import {
    registerNarrationDocumentSync,
    retryAllPendingNarrationDocuments,
} from '@/features/documents/services/narrationDocuments';

const RETRY_INTERVAL_MS = 30 * 1000;

registerNarrationDocumentSync();

/**
 * Keeps the device and the server in step:
 *  - after sign-in: push anything queued earlier, then download everything the user can see into the cache;
 *  - when the connection comes back, or the app returns to the foreground: same;
 *  - while changes are still waiting: retry every 30 seconds.
 * Mounted once, below the auth gate's data (Redux), above the navigator.
 */
export default function SyncProvider({ children }: PropsWithChildren) {
    const dispatch = useAppDispatch();
    const userId = useAppSelector(state => state.auth.user?.id ?? null);

    useEffect(() => {
        if (!userId) {
            return;
        }
        const stopWatching = startOutboxWatcher();

        const run = async (force: boolean) => {
            await flushOutbox();
            await retryAllPendingNarrationDocuments().catch(() => { });
            await prefetchAll(force);
        };

        void run(true).catch((error) => { console.error('Failed to sync and prefetch data: ', error) });

        // Anything that synced changes what the case list shows (record ids and file references change).
        const stopSynced = subscribeSynced(() => {
            dispatch(loadCases());
            void prefetchAll(true).catch((error) => { console.error('Failed to sync and prefetch data: ', error) });
        });

        let wasOnline = true;
        const stopNet = NetInfo.addEventListener(state => {
            const online = state.isConnected !== false && state.isInternetReachable !== false;
            if (online && !wasOnline) {
                void run(false).catch((error) => { console.error('Failed to sync and prefetch data: ', error) });;
            }
            wasOnline = online;
        });

        const stopApp = AppState.addEventListener('change', next => {
            if (next === 'active') {
                void run(false).catch((error) => { console.error('Failed to sync and prefetch data: ', error) });
            }
        });

        const timer = setInterval(() => {
            const s = getSyncSnapshot();
            if (s.pending > 0 && !s.syncing && !s.authRequired) {
                void flushOutbox().catch((error) => { console.error('Failed to sync and prefetch data: ', error) });
            }
        }, RETRY_INTERVAL_MS);

        return () => {
            stopWatching();
            stopSynced();
            stopNet();
            stopApp.remove();
            clearInterval(timer);
        };
    }, [userId, dispatch]);

    return <>{children}</>;
}
