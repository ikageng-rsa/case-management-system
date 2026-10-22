import React, { useEffect, useState } from 'react';
import { Alert, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useLocalSearchParams, useRouter } from 'expo-router';
import Button from '@/components/Button';
import Input from '@/components/Input';
import ChipSelect from '@/components/ChipSelect';
import { createDarzation } from '@/api/endpoint/darzations.api';
import { getApiErrorMessage } from '@/api/errors';
import { fetchCases } from '@/api/endpoint/cases.api';
import { useAppSelector } from '@/hooks/useAppDispatch';
import { useAssignableUsers } from '@/hooks/useAssignableUsers';
import { canEditRecords } from '@/constants/roles';
import { visibleCasesFor } from '@/utils/caseAccess';
import { isValidIsoDate } from '@/utils/validators';
import { colors, spacing, typography } from '@/theme/index';
import { Case, DarzationPriority } from '@/models/index';

const PRIORITIES: DarzationPriority[] = ['Low', 'Medium', 'High', 'Urgent'];

export default function NewDarzationScreen() {
    const router = useRouter();
    const params = useLocalSearchParams<{ caseId?: string }>();
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
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        fetchCases()
            .then(all => setCases(visibleCasesFor(all, me)))
            .catch(() => setCases([]));
    }, [me]);

    if (me && !canEditRecords(me.role)) {
        return (
            <View style={styles.container}>
                <Text style={styles.note}>Your role can view the diary but not create items.</Text>
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
            router.replace({ pathname: './diary/[darzationId]', params: { darzationId: created.id } });
        } catch (e) {
            Alert.alert('Could not save', getApiErrorMessage(e, 'Please try again.'));
        } finally {
            setLoading(false);
        }
    };

    return (
        <ScrollView style={styles.container} contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
            <ChipSelect
                label="Case *"
                options={cases.map(c => ({ value: c.id, label: `${c.fileReference} · ${c.clientName}` }))}
                selected={caseId}
                onChange={setCaseId}
            />
            <Input label="Title *" value={title} onChangeText={setTitle} />
            <Input label="Next action *" value={nextAction} onChangeText={setNextAction} multiline style={styles.multiline} />
            <Input label="Due date * (YYYY-MM-DD)" value={dueDate} onChangeText={setDueDate} autoCapitalize="none" />
            <ChipSelect label="Priority" options={PRIORITIES.map(p => ({ value: p, label: p }))} selected={priority} onChange={setPriority} />
            <ChipSelect
                label="Assigned users"
                multiple
                options={users.map((u:any) => ({ value: u.id, label: u.fullName }))}
                selected={assigned}
                onChange={setAssigned}
            />

            <Text style={styles.section}>Steps</Text>
            {steps.map((s, i) => (
                <Text key={i} style={styles.step}>
                    {i + 1}. {s}
                </Text>
            ))}
            <Input label="Add a step" value={stepDraft} onChangeText={setStepDraft} onSubmitEditing={addStep} returnKeyType="done" />
            <Button label="+ Add step" variant="secondary" onPress={addStep} style={styles.addStep} />

            <Button label="Save diary item" onPress={submit} loading={loading} />
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
