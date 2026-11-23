import React, { useEffect, useState } from 'react';
import { ScrollView, StyleSheet, Text, View } from 'react-native';
import { useLocalSearchParams, useRouter } from 'expo-router';
import Button from '@/components/Button';
import Input from '@/components/Input';
import ChipSelect from '@/components/ChipSelect';
import LoadingSpinner from '@/components/LoadingSpinner';
import { createDarzation, fetchDarzationById, updateDarzation } from '@/api/endpoint/darzations.api';
import { fetchCases } from '@/api/endpoint/cases.api';
import { getApiErrorMessage, getFieldErrors } from '@/api/errors';
import { REMINDER_OPTIONS } from '@/features/darzation/reminderOptions';
import { useAppSelector } from '@/hooks/useAppDispatch';
import { useAssignableUsers } from '@/hooks/useAssignableUsers';
import { canEditRecords } from '@/constants/roles';
import { visibleCasesFor } from '@/utils/caseAccess';
import { buildDueAt, DEFAULT_DUE_TIME, isValidTime, splitDueAt } from '@/utils/dueAt';
import { isValidIsoDate } from '@/utils/validators';
import { notifyError } from '@/utils/notify';
import { colors, spacing, typography } from '@/theme/index';
import { Case, Darzation } from '@/models/index';

const USUAL = 'usual';

/** Adds a diary entry to a matter, or edits one when the route carries a `darzationId`. */
export default function NewDarzationScreen() {
  const router = useRouter();
  const params = useLocalSearchParams<{ caseId?: string; darzationId?: string }>();
  const editId = params.darzationId;
  const editing = Boolean(editId);
  const me = useAppSelector(state => state.auth.user);
  const users = useAssignableUsers();

  const [cases, setCases] = useState<Case[]>([]);
  const [caseId, setCaseId] = useState<string[]>(params.caseId ? [params.caseId] : []);
  const [original, setOriginal] = useState<Darzation | null>(null);
  const [body, setBody] = useState('');
  const [day, setDay] = useState('');
  const [time, setTime] = useState(DEFAULT_DUE_TIME);
  const [assigned, setAssigned] = useState<string[]>(me ? [me.id] : []);
  const [reminder, setReminder] = useState<string[]>([USUAL]);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [loading, setLoading] = useState(false);
  const [loaded, setLoaded] = useState(!editing);
  const [missing, setMissing] = useState(false);

  // Edit mode: fill the form from the saved entry (network first, on-device copy when offline).
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
        setOriginal(d);
        setCaseId([d.caseId]);
        setBody(d.body ?? d.title ?? '');
        const parts = d.dueAt ? splitDueAt(d.dueAt) : { day: d.dueDate, time: DEFAULT_DUE_TIME };
        setDay(parts.day);
        setTime(parts.time);
        setAssigned(d.assigneeId ? [d.assigneeId] : (d.assignedUserIds ?? []));
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
  if (missing || (editing && original?.status === 'Done')) {
    return (
      <View style={styles.container}>
        <Text style={styles.note}>{missing ? 'This diary entry is not available to edit.' : 'This entry is done, so it can no longer be changed.'}</Text>
      </View>
    );
  }
  if (me && !canEditRecords(me.role)) {
    return (
      <View style={styles.container}>
        <Text style={styles.note}>Your role can view the diary but not add or edit entries.</Text>
      </View>
    );
  }

  const submit = async () => {
    const next: Record<string, string> = {};
    if (!editing && !caseId[0]) next.matter = 'Choose the matter this is for.';
    if (!body.trim()) next.body = 'Write what has to be done.';
    if (!isValidIsoDate(day)) next.due_at = 'Use the date format YYYY-MM-DD (e.g. 2026-11-05).';
    else if (!isValidTime(time)) next.due_at = 'Use the 24-hour time format HH:MM (e.g. 09:00).';
    setErrors(next);
    if (Object.keys(next).length > 0) {
      return;
    }
    const dueAt = buildDueAt(day, time);
    if (!dueAt) {
      setErrors({ due_at: 'That date and time do not exist.' });
      return;
    }

    setLoading(true);
    try {
      if (original) {
        await updateDarzation(original, {
          body: body.trim(),
          dueAt,
          assignedTo: assigned[0] ?? original.assigneeId ?? me?.id ?? '',
          narrationId: original.narrationId ?? null,
        });
        router.back();
        return;
      }
      const assignee = assigned[0];
      const choice = REMINDER_OPTIONS.find(o => o.value === reminder[0]);
      const created = await createDarzation({
        caseId: caseId[0],
        body: body.trim(),
        dueAt,
        // Left out when it is the person themself: the server then defaults to whoever is diarising.
        assignedTo: assignee && assignee !== me?.id ? assignee : undefined,
        // Left out = the user's usual reminder; a chosen option (or "none") is sent as is.
        remindBeforeMinutes: reminder[0] === USUAL ? undefined : (choice?.minutes ?? undefined),
        ownerId: me?.id ?? '',
        assigneeName: users.find(u => u.id === assignee)?.fullName,
      });
      router.replace({ pathname: '/diary/[darzationId]', params: { darzationId: created.id } });
    } catch (e) {
      // 422 names the field at fault; show it beside that field, and anything else as a message.
      const fields = getFieldErrors(e);
      if (Object.keys(fields).length > 0) {
        setErrors({ ...fields, ...(fields.assigned_to ? { assigned_to: fields.assigned_to } : {}) });
      } else {
        notifyError('Could not save', getApiErrorMessage(e, 'Please try again.'));
      }
    } finally {
      setLoading(false);
    }
  };

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
      {editing ? null : (
        <>
          <ChipSelect
            label="Matter *"
            options={cases.map(c => ({ value: c.id, label: `${c.fileReference} · ${c.clientName}` }))}
            selected={caseId}
            onChange={setCaseId}
          />
          {errors.matter ? <Text style={styles.error}>{errors.matter}</Text> : null}
        </>
      )}
      <Input label="What has to be done *" value={body} onChangeText={setBody} multiline style={styles.multiline} error={errors.body} />
      <Input label="Due date * (YYYY-MM-DD)" value={day} onChangeText={setDay} autoCapitalize="none" keyboardType="numbers-and-punctuation" />
      <Input label="Due time * (24-hour HH:MM)" value={time} onChangeText={setTime} autoCapitalize="none" keyboardType="numbers-and-punctuation" error={errors.due_at} />
      <ChipSelect
        label="Assigned to"
        options={users.map(u => ({ value: u.id, label: u.fullName }))}
        selected={assigned}
        onChange={setAssigned}
      />
      {errors.assigned_to ? <Text style={styles.error}>{errors.assigned_to}</Text> : null}
      {editing ? null : (
        <ChipSelect
          label="Reminder"
          options={[{ value: USUAL, label: 'My usual reminder' }, ...REMINDER_OPTIONS.map(o => ({ value: o.value, label: o.label }))]}
          selected={reminder}
          onChange={setReminder}
        />
      )}
      {errors.remind_before_minutes ? <Text style={styles.error}>{errors.remind_before_minutes}</Text> : null}

      <Button label={editing ? 'Save changes' : 'Add to diary'} onPress={submit} loading={loading} />
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.background },
  content: { padding: spacing.lg, paddingBottom: spacing.xl * 2 },
  multiline: { minHeight: 80, textAlignVertical: 'top' },
  error: { ...typography.caption, color: colors.danger, marginBottom: spacing.md },
  note: { ...typography.body, color: colors.textSecondary, padding: spacing.lg },
});
