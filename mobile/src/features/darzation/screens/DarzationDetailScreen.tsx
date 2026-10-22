import React, { useCallback, useEffect, useState } from 'react';
import { Alert, Pressable, ScrollView, StyleSheet, Switch, Text, View } from 'react-native';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { fetchDarzationById, toggleDarzationStep, updateDarzation } from '@/api/endpoint/darzations.api';
import { fetchNarrationsForDarzation } from '@/api/endpoint/narrations.api';
import Card from '@/components/Card';
import Button from '@/components/Button';
import ChipSelect from '@/components/ChipSelect';
import DetailRow from '@/components/DetailRow';
import LoadingSpinner from '@/components/LoadingSpinner';
import PriorityBadge from '@/components/PriorityBadge';
import SectionHeader from '@/components/SectionHeader';
import SyncBadge from '@/components/SyncBadge';
import { useOnSynced } from '@/hooks/useSyncStatus';
import { getApiErrorMessage } from '@/api/errors';
import { useAppSelector } from '@/hooks/useAppDispatch';
import { useAssignableUsers } from '@/hooks/useAssignableUsers';
import { canEditRecords } from '@/constants/roles';
import { computeOverdue } from '@/models/Darzation';
import { colors, spacing, typography } from '@/theme/index';
import { Darzation, DarzationStatus, Narration } from '@/models/index';

const STATUSES: DarzationStatus[] = ['Pending', 'In Progress', 'Done'];

export default function DarzationDetailScreen() {
    const router = useRouter();
    const { darzationId } = useLocalSearchParams<{ darzationId: string }>();
    const me = useAppSelector(state => state.auth.user);
    const users = useAssignableUsers();
    const [item, setItem] = useState<Darzation | null>(null);
    const [narrations, setNarrations] = useState<Narration[]>([]);
    const [loading, setLoading] = useState(true);

    const load = useCallback(async () => {
        try {
            const d = await fetchDarzationById(darzationId);
            setItem(d);
            setNarrations(await fetchNarrationsForDarzation(d.caseId, d.id).catch(() => []));
        } catch {
            setItem(null);
        } finally {
            setLoading(false);
        }
    }, [darzationId]);

    // Fetch-on-mount: setState runs after awaited network I/O, not synchronously.
    useEffect(() => {
        // eslint-disable-next-line react-hooks/set-state-in-effect
        load();
    }, [load]);
    useOnSynced(load);

    if (loading) {
        return <LoadingSpinner />;
    }
    if (!item) {
        return (
            <View style={styles.container}>
                <Text style={styles.note}>This diary item is not available.</Text>
            </View>
        );
    }

    const canEdit = me ? canEditRecords(me.role) : false;
    const due = item.dueDate || item.nextActionDate;
    const overdue = computeOverdue(due, item.status ?? 'Pending');
    const nameOf = (id: string) => users.find(u => u.id === id)?.fullName ?? id;

    const toggleStep = async (stepId: string, done: boolean) => {
        // Optimistic: flip locally, roll back if the API rejects it.
        const previous = item;
        setItem({ ...item, steps: item.steps.map(s => (s.id === stepId ? { ...s, done } : s)) });
        try {
            setItem(await toggleDarzationStep(item.id, stepId, done));
        } catch (e) {
            setItem(previous);
            Alert.alert('Could not update step', getApiErrorMessage(e, 'Please try again.'));
        }
    };

    const setStatus = async (status: DarzationStatus) => {
        try {
            setItem(await updateDarzation(item.id, { status }));
        } catch (e) {
            Alert.alert('Could not update status', getApiErrorMessage(e, 'Please try again.'));
        }
    };

    return (
        <ScrollView style={styles.container} contentContainerStyle={styles.content}>
            <Text style={styles.title}>{item.title || item.nextAction}</Text>
            <View style={styles.badges}>
                {item.priority ? <PriorityBadge priority={item.priority} /> : null}
                {overdue ? <Text style={styles.overdueTag}>OVERDUE</Text> : null}
                <SyncBadge state={item.syncState} />
            </View>

            <Card style={styles.card}>
                <DetailRow label="Next action" value={item.nextAction} />
                <DetailRow label="Due" value={due} />
                <DetailRow label="Status" value={item.status ?? 'Pending'} />
                <DetailRow label="Owner" value={item.ownerId ? nameOf(item.ownerId) : undefined} />
                <DetailRow label="Assigned to" value={item.assignedUserIds?.map(nameOf).join(', ')} />
                <Pressable onPress={() => router.push({ pathname: '/cases/[caseId]', params: { caseId: item.caseId } })}>
                    <Text style={styles.link}>Open case →</Text>
                </Pressable>
            </Card>

            {canEdit ? (
                <ChipSelect
                    label="Update status"
                    options={STATUSES.map(s => ({ value: s, label: s }))}
                    selected={[item.status ?? 'Pending']}
                    onChange={([s]:any) => s && setStatus(s)}
                />
            ) : null}

            <SectionHeader title="Steps" />
            {item.steps?.length ? (
                <Card>
                    {item.steps.map(step => (
                        <View key={step.id} style={styles.stepRow}>
                            <Text style={[styles.stepLabel, step.done && styles.stepDone]}>{step.label}</Text>
                            <Switch value={step.done} disabled={!canEdit} onValueChange={v => toggleStep(step.id, v)} />
                        </View>
                    ))}
                </Card>
            ) : (
                <Text style={styles.note}>No steps defined.</Text>
            )}

            <SectionHeader title="Narrations" />
            {narrations.length === 0 ? <Text style={styles.note}>No work logged against this item yet.</Text> : null}
            {narrations.map(n => (
                <Pressable
                    key={n.id}
                    onPress={() =>
                        router.push({ pathname: './cases/[caseId]/narrations/[narrationId]', params: { caseId: n.caseId, narrationId: n.id } })
                    }
                >
                    <Card>
                        <Text style={styles.stepLabel}>{n.title || n.activityType}</Text>
                        <Text style={styles.note}>{n.timeSpentMinutes ?? 0} min · {n.description}</Text>
                    </Card>
                </Pressable>
            ))}
            {canEdit ? (
                <Button
                    label="+ Log work on this item"
                    onPress={() =>
                        router.push({ pathname: '/cases/[caseId]/add-activity', params: { caseId: item.caseId, darzationId: item.id } })
                    }
                />
            ) : null}
        </ScrollView>
    );
}

const styles = StyleSheet.create({
    container: { flex: 1, backgroundColor: colors.background },
    content: { padding: spacing.md, paddingBottom: spacing.xl * 2 },
    title: { ...typography.h1, color: colors.navy },
    badges: { flexDirection: 'row', gap: spacing.sm, alignItems: 'center', marginVertical: spacing.sm },
    overdueTag: { ...typography.caption, color: colors.red, fontWeight: '700' },
    card: { marginTop: spacing.sm },
    link: { ...typography.body, color: colors.navy, fontWeight: '600', marginTop: spacing.sm },
    stepRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', paddingVertical: spacing.xs },
    stepLabel: { ...typography.body, color: colors.textPrimary, flex: 1, fontWeight: '600' },
    stepDone: { textDecorationLine: 'line-through', color: colors.textMuted },
    note: { ...typography.body, color: colors.textSecondary, paddingVertical: spacing.xs },
});
