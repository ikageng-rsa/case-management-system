import React, { useEffect, useState } from 'react';
import { Alert, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useLocalSearchParams, useRouter } from 'expo-router';
import Button from '@/components/Button';
import Input from '@/components/Input';
import ChipSelect from '@/components/ChipSelect';
import { createDarzation, fetchDarzationById, updateDarzation } from '@/api/endpoint/darzations.api';
import LoadingSpinner from '@/components/LoadingSpinner';
import { getApiErrorMessage } from '@/api/errors';
import { fetchCases } from '@/api/endpoint/cases.api';
import { useAppSelector } from '@/hooks/useAppDispatch';
import { useAssignableUsers } from '@/hooks/useAssignableUsers';
import { canEditRecords } from '@/constants/roles';
import { visibleCasesFor } from '@/utils/caseAccess';
import { isValidIsoDate } from '@/utils/validators';
import { colors, spacing, typography } from '@/theme/index';
import { Case, DarzationPriority, DarzationStatus } from '@/models/index';

const PRIORITIES: DarzationPriority[] = ['Low', 'Medium', 'High', 'Urgent'];
const STATUSES: DarzationStatus[] = ['Pending', 'In Progress', 'Done'];

/** Creates a diary item, or edits one when the route carries a `darzationId`. */
export default function NewDarzationScreen() {
    const router = useRouter();
    const params = useLocalSearchParams<{ caseId?: string; darzationId?: string }>();
    const editId = params.darzationId;
    const editing = Boolean(editId);
    const me = useAppSelector(state => state.auth.user);
    const users = useAssignableUsers();

    const [cases, setCases] = useState<Case[]>([]);
    const [caseId, setCaseId] = useState<string[]>(params.caseId ? [params.caseId] : []);
    const [title, setTitle] = useState('');
    const [nextAction, setNextAction] = useState('');
    const [dueDate, setDueDate] = useState('');
    const [priority, setPriority] = useState<DarzationPriority[]>(['Medium']);
    const [assigned, setAssigned] = useState<string[]>(me ? [me.id] : []);
    const [stepDraft, setStepDraft] = useState('');
    const [steps, setSteps] = useState<string[]>([]);
    const [existingSteps, setExistingSteps] = useState<{ id: string; label: string; done: boolean }[]>([]);
    const [status, setStatus] = useState<DarzationStatus[]>(['Pending']);
    const [loading, setLoading] = useState(false);
    const [loaded, setLoaded] = useState(!editing);
    const [missing, setMissing] = useState(false);

    // Edit mode: fill the form from the saved item (network first, on-device copy when offline).
    useEffect(() => {
        if (!editId) {
            return;
        }
        let active = true;
        fetchDarzationById(editId)
            .then(d => {
                if (!active) {
                    return;
                }
                setCaseId([d.caseId]);
                setTitle(d.title ?? '');
                setNextAction(d.nextAction ?? '');
                setDueDate((d.dueDate || d.nextActionDate || '').slice(0, 10));
                setPriority([d.priority ?? 'Medium']);
                setStatus([d.status ?? 'Pending']);
                setAssigned(d.assignedUserIds ?? []);
                setExistingSteps(d.steps ?? []);
            })
            .catch(() => active && setMissing(true))
            .finally(() => active && setLoaded(true));
        return () => {
            active = false;
        };
    }, [editId]);

    useEffect(() => {
        fetchCases()
            .then(all => setCases(visibleCasesFor(all, me)))
            .catch(() => setCases([]));
    }, [me]);

    if (!loaded) {
        return <LoadingSpinner />;
    }
    if (missing) {
        return (
            <View style={styles.container}>
                <Text style={styles.note}>This diary item is not available to edit.</Text>
            </View>
        );
    }
    if (me && !canEditRecords(me.role)) {
        return (
            <View style={styles.container}>
                <Text style={styles.note}>Your role can view the diary but not create or edit items.</Text>
            </View>
        );
    }

    const addStep = () => {
        if (stepDraft.trim()) {
            setSteps(s => [...s, stepDraft.trim()]);
            setStepDraft('');
        }
    };

    const submit = async () => {
        if (!caseId[0] || !title.trim() || !nextAction.trim()) {
            Alert.alert('Missing info', 'Choose a case and enter a title and the next action.');
            return;
        }
        if (!isValidIsoDate(dueDate)) {
            Alert.alert('Invalid due date', 'Use the format YYYY-MM-DD (e.g. 2026-11-05).');
            return;
        }
        setLoading(true);
        try {
            if (editId) {
                await updateDarzation(editId, {
                    title: title.trim(),
                    nextAction: nextAction.trim(),
                    nextActionDate: dueDate,
                    dueDate,
                    priority: priority[0] ?? 'Medium',
                    status: status[0] ?? 'Pending',
                    assignedUserIds: assigned,
                    // Existing steps keep their ids and ticks; new ones are appended.
                    steps: [...existingSteps, ...steps.map((label, i) => ({ id: `new-${i + 1}`, label, done: false }))],
                });
                router.back();
                return;
            }
            const created = await createDarzation({
                caseId: caseId[0],
                ownerId: me?.id ?? '',
                title: title.trim(),
                nextAction: nextAction.trim(),
                nextActionDate: dueDate,
                dueDate,
                priority: priority[0] ?? 'Medium',
                status: 'Pending',
                steps: steps.map((label, i) => ({ id: `step-${i + 1}`, label, done: false })),
                assignedUserIds: assigned,
                visibleToRoles: ['Admin', 'Director', 'CA', 'Secretary'],
            });
            router.replace({ pathname: '/diary/[darzationId]', params: { darzationId: created.id } });
        } catch (e) {
            Alert.alert('Could not save', getApiErrorMessage(e, 'Please try again.'));
        } finally {
            setLoading(false);
        }
    };

    return (
        <ScrollView style={styles.container} contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
            {editing ? null : (
                <ChipSelect
                    label="Case *"
                    options={cases.map(c => ({ value: c.id, label: `${c.fileReference} · ${c.clientName}` }))}
                    selected={caseId}
                    onChange={setCaseId}
                />
            )}
            <Input label="Title *" value={title} onChangeText={setTitle} />
            <Input label="Next action *" value={nextAction} onChangeText={setNextAction} multiline style={styles.multiline} />
            <Input label="Due date * (YYYY-MM-DD)" value={dueDate} onChangeText={setDueDate} autoCapitalize="none" />
            <ChipSelect label="Priority" options={PRIORITIES.map(p => ({ value: p, label: p }))} selected={priority} onChange={setPriority} />
            {editing ? <ChipSelect label="Status" options={STATUSES.map(v => ({ value: v, label: v }))} selected={status} onChange={setStatus} /> : null}
            <ChipSelect
                label="Assigned users"
                multiple
                options={users.map(u => ({ value: u.id, label: u.fullName }))}
                selected={assigned}
                onChange={setAssigned}
            />

            <Text style={styles.section}>Steps</Text>
            {existingSteps.map((s, i) => (
                <Text key={s.id} style={styles.step}>
                    {i + 1}. {s.label}
                    {s.done ? ' ✓' : ''}
                </Text>
            ))}
            {steps.map((s, i) => (
                <Text key={s.substring(i.toString().length,-1)} style={styles.step}>
                    {existingSteps.length + i + 1}. {s}
                </Text>
            ))}
            <Input label="Add a step" value={stepDraft} onChangeText={setStepDraft} onSubmitEditing={addStep} returnKeyType="done" />
            <Button label="+ Add step" variant="secondary" onPress={addStep} style={styles.addStep} />

            <Button label={editing ? 'Save changes' : 'Save diary item'} onPress={submit} loading={loading} />
        </ScrollView>
    );
}

const styles = StyleSheet.create({
    container: { flex: 1, backgroundColor: colors.background },
    content: { padding: spacing.lg, paddingBottom: spacing.xl * 2 },
    section: { ...typography.h3, color: colors.navy, marginBottom: spacing.sm },
    step: { ...typography.body, color: colors.textPrimary, marginBottom: spacing.xs },
    addStep: { marginBottom: spacing.lg },
    multiline: { minHeight: 70, textAlignVertical: 'top' },
    note: { ...typography.body, color: colors.textSecondary, padding: spacing.lg },
});
