import React, { useCallback, useState } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useFocusEffect, useLocalSearchParams, useRouter } from 'expo-router';
import { completeDarzation, fetchDarzationById, updateDiaryReminder } from '@/api/endpoint/darzations.api';
import { fetchNarrationsForCase } from '@/api/endpoint/narrations.api';
import { getApiErrorMessage } from '@/api/errors';
import Card from '@/components/Card';
import Button from '@/components/Button';
import ChipSelect from '@/components/ChipSelect';
import DetailRow from '@/components/DetailRow';
import LoadingSpinner from '@/components/LoadingSpinner';
import SectionHeader from '@/components/SectionHeader';
import SyncBadge from '@/components/SyncBadge';
import { StageBadge } from '@/ui';
import { REMINDER_OPTIONS, reminderValueFor } from '@/features/darzation/reminderOptions';
import { useOnSynced } from '@/hooks/useSyncStatus';
import { useAppSelector } from '@/hooks/useAppDispatch';
import { useAssignableUsers } from '@/hooks/useAssignableUsers';
import { canEditRecords } from '@/constants/roles';
import { formatDue } from '@/utils/dueAt';
import { notifyError, notifySuccess, notifyWarning } from '@/utils/notify';
import { colors, fontFamily, spacing, typography } from '@/theme/index';
import { Darzation, Narration } from '@/models/index';

export default function DarzationDetailScreen() {
    const router = useRouter();
    const { darzationId } = useLocalSearchParams<{ darzationId: string }>();
    const me = useAppSelector(state => state.auth.user);
    const users = useAssignableUsers();
    const [item, setItem] = useState<Darzation | null>(null);
    const [linked, setLinked] = useState<Narration | null>(null);
    const [loading, setLoading] = useState(true);
    const [busy, setBusy] = useState(false);

    const load = useCallback(async () => {
        try {
            const d = await fetchDarzationById(darzationId);
            setItem(d);
            if (d.narrationId) {
                const all = await fetchNarrationsForCase(d.caseId).catch(() => [] as Narration[]);
                setLinked(all.find(n => n.id === d.narrationId) ?? null);
            } else {
                setLinked(null);
            }
        } catch {
            setItem(null);
        } finally {
            setLoading(false);
        }
    }, [darzationId]);

    // Reload whenever the screen is shown again (e.g. back from the edit form), not only on first mount.
    useFocusEffect(
        useCallback(() => {
            load();
        }, [load]),
    );
    useOnSynced(load);

    if (loading) {
        return <LoadingSpinner />;
    }
    if (!item) {
        return (
            <View style={styles.container}>
                <Text style={styles.note}>This diary entry is not available.</Text>
            </View>
        );
    }

    const done = item.status === 'Done';
    const canEdit = me ? canEditRecords(me.role) : false;
    const mine = Boolean(me && item.assigneeId === me.id);
    const nameOf = (id?: string) => (id ? (users.find(u => u.id === id)?.fullName ?? item.assigneeName ?? id) : undefined);
    const savedOnServer = !item.id.startsWith('tmp_');

    const run = async (task: () => Promise<unknown>, success?: string) => {
        setBusy(true);
        try {
            await task();
            if (success) {
                notifySuccess(success);
            }
            await load();
        } catch (e) {
            // 409 explains itself ("not due until …", "this entry is done"), 403 says it is not the user's entry.
            notifyWarning('Not done', getApiErrorMessage(e, 'Please try again.'));
        } finally {
            setBusy(false);
        }
    };

    const markDone = () => run(() => completeDarzation(item), 'Marked as done');

    const setReminder = (values: string[]) => {
        const option = REMINDER_OPTIONS.find(o => o.value === values[0]);
        if (!option) {
            notifyError('Could not change the reminder', 'Choose one of the options.');
            return;
        }
        run(() => updateDiaryReminder(item, option.minutes), 'Reminder changed');
    };

    return (
        <ScrollView style={styles.container} contentContainerStyle={styles.content}>
            <Text style={styles.title}>{item.body || item.title}</Text>
            <View style={styles.badges}>
                {item.overdue ? <StageBadge tone="danger">Overdue</StageBadge> : null}
                {!item.overdue && item.isDue && !done ? <StageBadge tone="accent">Due now</StageBadge> : null}
                {done ? <StageBadge tone="success">Done</StageBadge> : null}
                <SyncBadge state={item.syncState} />
            </View>

            <Card style={styles.card}>
                <DetailRow label="Due" value={formatDue(item.dueAt) || item.dueDate} />
                <DetailRow label="Assigned to" value={nameOf(item.assigneeId)} />
                <DetailRow label="Matter" value={[item.matterReference, item.matterTitle].filter(Boolean).join(' · ') || undefined} />
                <DetailRow label="Reminder" value={item.reminderLabel ?? (item.remindBeforeMinutes === null ? 'None' : undefined)} />
                {done ? <DetailRow label="Done" value={`${formatDue(item.completedAt ?? undefined)}${item.completedByName ? ` · ${item.completedByName}` : ''}`} /> : null}
                <Pressable onPress={() => router.push({ pathname: '/cases/[caseId]', params: { caseId: item.caseId } })} accessibilityRole="link">
                    <Text style={styles.link}>Open matter →</Text>
                </Pressable>
            </Card>

            {!done && savedOnServer ? (
                <>
                    {!item.isDue ? <Text style={styles.note}>It can be marked done from {formatDue(item.dueAt) || item.dueDate}.</Text> : null}
                    <Button label="Mark as done" onPress={markDone} loading={busy} disabled={!item.isDue || busy} />
                    {canEdit ? (
                        <Button
                            label="Edit entry"
                            variant="secondary"
                            onPress={() => router.push({ pathname: '/diary/edit', params: { darzationId: item.id } })}
                            style={styles.action}
                        />
                    ) : null}
                    {mine ? (
                        <View style={styles.reminder}>
                            <ChipSelect label="Remind me" options={REMINDER_OPTIONS.map(o => ({ value: o.value, label: o.label }))} selected={reminderValueFor(item.remindBeforeMinutes)} onChange={setReminder} />
                        </View>
                    ) : null}
                </>
            ) : null}

            <SectionHeader title="Work logged" />
            {linked ? (
                <Pressable
                    onPress={() => router.push({ pathname: '/cases/[caseId]/narrations/[narrationId]', params: { caseId: linked.caseId, narrationId: linked.id } })}
                >
                    <Card>
                        <Text style={styles.stepLabel}>{linked.activityName || linked.activityType}</Text>
                        <Text style={styles.note}>{linked.description}</Text>
                    </Card>
                </Pressable>
            ) : (
                <Text style={styles.note}>No work is linked to this entry.</Text>
            )}
            {canEdit ? (
                <Button
                    label="+ Log work on this matter"
                    variant="secondary"
                    onPress={() => router.push({ pathname: '/cases/[caseId]/add-activity', params: { caseId: item.caseId } })}
                    style={styles.action}
                />
            ) : null}
        </ScrollView>
    );
}

const styles = StyleSheet.create({
    container: { flex: 1, backgroundColor: colors.background },
    content: { padding: spacing.md, paddingBottom: spacing.xl * 2 },
    title: { ...typography.h2, color: colors.navy },
    badges: { flexDirection: 'row', gap: spacing.sm, alignItems: 'center', marginVertical: spacing.sm },
    card: { marginTop: spacing.sm },
    action: { marginTop: spacing.sm },
    reminder: { marginTop: spacing.md },
    link: { ...typography.body, color: colors.navy, fontFamily: fontFamily.sansSemibold, marginTop: spacing.sm },
    stepLabel: { ...typography.body, color: colors.textPrimary, fontFamily: fontFamily.sansSemibold },
    note: { ...typography.body, color: colors.textSecondary, paddingVertical: spacing.xs },
});
