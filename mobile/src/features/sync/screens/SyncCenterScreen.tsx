import React, { useCallback, useEffect, useState } from 'react';
import { Alert, ScrollView, StyleSheet, Text, View } from 'react-native';
import Button from '@/components/Button';
import Card from '@/components/Card';
import { useNetInfo } from '@/hooks/useNetInfo';
import { useAppSelector } from '@/hooks/useAppDispatch';
import { useSyncStatus } from '@/hooks/useSyncStatus';
import { OFFLINE_EDITS_ENABLED } from '@/offline/policy';
import { cacheMeta } from '@/offline/cache';
import { clearOfflineData } from '@/offline/clear';
import { listOutbox, subscribeOutbox } from '@/offline/outbox';
import { prefetchAll } from '@/offline/prefetch';
import { discardEntry, flushOutbox, retryEntry } from '@/offline/syncEngine';
import { OutboxEntry } from '@/offline/types';
import { colors, radius, spacing, typography } from '@/theme/index';

const STATUS_LABEL: Record<OutboxEntry['status'], string> = {
    pending: 'Waiting to sync',
    failed: 'Needs attention',
    blocked: 'On hold',
};

const when = (iso?: string | null) => (iso ? new Date(iso).toLocaleString() : 'never');

/** Everything saved on this device that hasn't reached the server yet, plus controls for the offline cache. */
export default function SyncCenterScreen() {
    const { isOnline } = useNetInfo();
    const status = useSyncStatus();
    const user = useAppSelector(state => state.auth.user);
    const [entries, setEntries] = useState<OutboxEntry[]>([]);
    const [lastPrefetch, setLastPrefetch] = useState<string | undefined>();
    const [busy, setBusy] = useState(false);

    const load = useCallback(async () => {
        setEntries(await listOutbox());
        setLastPrefetch((await cacheMeta()).lastPrefetchAt);
    }, []);

    useEffect(() => {
        // eslint-disable-next-line react-hooks/set-state-in-effect
        load();
        return subscribeOutbox(() => {
            load();
        });
    }, [load]);

    const run = async (task: () => Promise<unknown>) => {
        setBusy(true);
        try {
            await task();
        } finally {
            setBusy(false);
            load();
        }
    };

    const confirmDiscard = (entry: OutboxEntry) =>
        Alert.alert(
            'Discard this change?',
            `"${entry.label}" has not been saved to the server and will be lost${entry.op === 'create' ? ', along with anything you added to it' : ''}.`,
            [
                { text: 'Keep', style: 'cancel' },
                { text: 'Discard', style: 'destructive', onPress: () => run(() => discardEntry(entry.id)) },
            ],
        );

    const confirmClear = () =>
        Alert.alert(
            'Remove offline data?',
            entries.length > 0
                ? `This deletes the offline copy AND ${entries.length} unsynced change${entries.length === 1 ? '' : 's'} from this device. Sync first if you can.`
                : 'This deletes the offline copy of your cases, diary and narrations from this device. It is downloaded again the next time you are online.',
            [
                { text: 'Cancel', style: 'cancel' },
                {
                    text: 'Remove',
                    style: 'destructive',
                    onPress: () =>
                        run(async () => {
                            await clearOfflineData(user?.id);
                            if (isOnline) {
                                await prefetchAll(true);
                            }
                        }),
                },
            ],
        );

    return (
        <ScrollView style={styles.container} contentContainerStyle={styles.content}>
            <Card>
                <Text style={styles.heading}>{isOnline ? 'Online' : 'Offline'}</Text>
                <Text style={styles.line}>Last sync: {when(status.lastSyncAt)}</Text>
                <Text style={styles.line}>Offline copy refreshed: {when(lastPrefetch)}</Text>
                {status.lastError ? <Text style={styles.warn}>{status.lastError}</Text> : null}
                {status.authRequired ? (
                    <Text style={styles.warn}>
                        Your saved sign-in has expired. Sign out, then sign in again while online — your unsynced changes are kept.
                    </Text>
                ) : null}
                <Text style={styles.note}>
                    Offline you can add cases, diary items and narrations. Deleting is not available offline
                    {OFFLINE_EDITS_ENABLED ? '.' : ', and neither is editing records that already exist, so no one overwrites a colleague.'}
                </Text>
                <View style={styles.actions}>
                    <Button
                        label={status.syncing ? 'Syncing…' : 'Sync now'}
                        onPress={() => run(async () => {
                            await flushOutbox();
                            await prefetchAll(true);
                        })}
                        disabled={!isOnline || busy || status.syncing}
                        loading={busy}
                    />
                </View>
            </Card>

            <Text style={styles.section}>Waiting to sync ({entries.length})</Text>
            {entries.length === 0 ? (
                <Card>
                    <Text style={styles.line}>Everything is saved to the server.</Text>
                </Card>
            ) : (
                entries.map(entry => (
                    <Card key={entry.id}>
                        <Text style={styles.entryLabel}>{entry.label}</Text>
                        <Text style={[styles.entryStatus, entry.status !== 'pending' && styles.warn]}>
                            {STATUS_LABEL[entry.status]} · saved {when(entry.createdAt)}
                        </Text>
                        {entry.error ? <Text style={styles.warn}>{entry.error}</Text> : null}
                        <View style={styles.rowButtons}>
                            {entry.status !== 'pending' ? (
                                <Button label="Retry" variant="secondary" onPress={() => run(() => retryEntry(entry.id))} disabled={!isOnline || busy} style={styles.rowButton} />
                            ) : null}
                            <Button label="Discard" variant="secondary" onPress={() => confirmDiscard(entry)} disabled={busy} style={styles.rowButton} />
                        </View>
                    </Card>
                ))
            )}

            <Button label="Remove offline data from this device" variant="danger" onPress={confirmClear} disabled={busy} style={styles.clear} />
        </ScrollView>
    );
}

const styles = StyleSheet.create({
    container: { flex: 1, backgroundColor: colors.background },
    content: { padding: spacing.md, paddingBottom: spacing.xl },
    heading: { ...typography.h2, color: colors.navy },
    line: { ...typography.body, color: colors.textSecondary, marginTop: spacing.xs },
    note: { ...typography.caption, color: colors.textMuted, marginTop: spacing.sm },
    warn: { ...typography.caption, color: colors.red, marginTop: spacing.xs },
    actions: { marginTop: spacing.md },
    section: { ...typography.body, fontWeight: '700', color: colors.navy, marginTop: spacing.lg, marginBottom: spacing.sm },
    entryLabel: { ...typography.body, fontWeight: '700', color: colors.textPrimary },
    entryStatus: { ...typography.caption, color: colors.textSecondary, marginTop: 2 },
    rowButtons: { flexDirection: 'row', marginTop: spacing.sm },
    rowButton: { flex: 1, marginRight: spacing.sm, borderRadius: radius.sm },
    clear: { marginTop: spacing.xl },
});
