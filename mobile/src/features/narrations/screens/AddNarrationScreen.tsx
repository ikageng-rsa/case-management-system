import React, { useEffect, useMemo, useState } from 'react';
import { ScrollView, StyleSheet, Text, View } from 'react-native';
import { useLocalSearchParams, useRouter } from 'expo-router';
import * as DocumentPicker from 'expo-document-picker';
import Button from '@/components/Button';
import Input from '@/components/Input';
import ChipSelect from '@/components/ChipSelect';
import LoadingSpinner from '@/components/LoadingSpinner';
import { fetchDocumentKinds, MAX_DOCUMENT_BYTES } from '@/api/endpoint/documents.api';
import { addNarration, fetchActivityTypes } from '@/api/endpoint/narrations.api';
import { MEASURE_LABEL } from '@/api/mappers/narration';
import { getApiErrorMessage } from '@/api/errors';
import { hasFeature } from '@/config/features';
import { attachDocumentToNarration, PickedDocumentFile } from '@/features/documents/services/narrationDocuments';
import { useAppSelector } from '@/hooks/useAppDispatch';
import { canEditRecords } from '@/constants/roles';
import { colors, fontFamily, spacing, typography } from '@/theme/index';
import { ActivityType, DocumentKind, TimeUnit } from '@/models/index';
import { notifyError, notifyWarning } from '@/utils/notify';

const todayIso = () => new Date().toISOString().slice(0, 10);

/**
 * Records a narration (a unit of work) on a matter. The matter's activity types say how the work is counted:
 * minutes, pages, kilometres or items. The v1 API has no edit for narrations, so this screen only adds.
 */
export default function AddNarrationScreen() {
  const router = useRouter();
  const { caseId, narrationId: editId } = useLocalSearchParams<{ caseId: string; darzationId?: string; narrationId?: string }>();
  const me = useAppSelector(state => state.auth.user);

  const [types, setTypes] = useState<ActivityType[] | null>(null);
  const [typeId, setTypeId] = useState<string[]>([]);
  const [description, setDescription] = useState('');
  const [hours, setHours] = useState('0');
  const [minutes, setMinutes] = useState('0');
  const [amount, setAmount] = useState('');
  const [timeTaken, setTimeTaken] = useState('');
  const [timeUnit, setTimeUnit] = useState<TimeUnit[]>(['minutes']);
  const [date, setDate] = useState(todayIso());
  const [files, setFiles] = useState<PickedDocumentFile[]>([]);
  const [kinds, setKinds] = useState<DocumentKind[]>([]);
  const [kind, setKind] = useState<string[]>([]);
  const [loading, setLoading] = useState(false);
  const [loadError, setLoadError] = useState<string | null>(null);

  useEffect(() => {
    fetchActivityTypes()
      .then(list => {
        setTypes(list);
        setTypeId(prev => (prev.length ? prev : list[0] ? [list[0].id] : []));
      })
      .catch(e => {
        setTypes([]);
        setLoadError(getApiErrorMessage(e, 'Could not load the activity types.'));
      });
  }, []);

  useEffect(() => {
    if (hasFeature('documents')) {
      fetchDocumentKinds().then(setKinds).catch(() => setKinds([]));
    }
  }, []);

  const activity = useMemo(() => types?.find(t => t.id === typeId[0]), [types, typeId]);

  if (types === null) {
    return <LoadingSpinner />;
  }
  if (editId) {
    return (
      <View style={styles.container}>
        <Text style={styles.note}>Editing an entry isn&apos;t available yet. Add a new entry, or ask the firm to correct it on the web system.</Text>
      </View>
    );
  }
  if (me && !canEditRecords(me.role)) {
    return (
      <View style={styles.container}>
        <Text style={styles.note}>Your role can view the activity log but not add entries.</Text>
      </View>
    );
  }

  const pickFiles = async () => {
    const result = await DocumentPicker.getDocumentAsync({ multiple: true, copyToCacheDirectory: true });
    if (result.canceled) {
      return;
    }
    const accepted: PickedDocumentFile[] = [];
    for (const asset of result.assets) {
      if (asset.size && asset.size > MAX_DOCUMENT_BYTES) {
        notifyError('File too large', `${asset.name} is over 50 MB and was skipped.`);
        continue;
      }
      accepted.push({ uri: asset.uri, name: asset.name, mimeType: asset.mimeType, size: asset.size });
    }
    setFiles(prev => [...prev, ...accepted]);
  };

  const submit = async () => {
    if (!activity) {
      notifyError('Choose an activity', 'Pick what kind of work this was.');
      return;
    }
    if (!description.trim()) {
      notifyError('Add a description', 'A short note on what was done is required.');
      return;
    }
    const quantity = activity.measure === 'minutes' ? (parseInt(hours, 10) || 0) * 60 + (parseInt(minutes, 10) || 0) : parseInt(amount, 10) || 0;
    if (quantity <= 0) {
      notifyError('Add an amount', `Enter how many ${(MEASURE_LABEL[activity.measure] ?? 'units').toLowerCase()}.`);
      return;
    }
    if (activity.increment > 1 && quantity % activity.increment !== 0) {
      notifyError('Check the amount', `${activity.name} is recorded in steps of ${activity.increment}.`);
      return;
    }
    const takenAmount = Number(timeTaken.replace(',', '.'));
    if (activity.timeOnTop && !(takenAmount > 0)) {
      notifyError('Add the time taken', `${activity.name} also needs how long the work took.`);
      return;
    }
    if (!/^\d{4}-\d{2}-\d{2}$/.test(date) || Number.isNaN(new Date(`${date}T12:00:00`).getTime())) {
      notifyError('Check the date', 'Use the format YYYY-MM-DD, for example 2026-10-26.');
      return;
    }
    // Midday on the chosen day, in the phone's time zone, so the date can't slip across midnight in transit.
    const occurredAt = new Date(`${date}T12:00:00`).toISOString();

    setLoading(true);
    try {
      const created = await addNarration({
        caseId,
        activity,
        description: description.trim(),
        quantity,
        occurredAt,
        ...(activity.timeOnTop ? { timeTaken: takenAmount, timeUnit: timeUnit[0] ?? 'minutes' } : {}),
      });

      let failed = 0;
      for (const file of hasFeature('documents') ? files : []) {
        try {
          await attachDocumentToNarration({
            caseId,
            narrationId: created.id,
            file,
            kind: kind[0] ?? kinds[0]?.id ?? 'evidence',
          });
        } catch {
          failed += 1;
        }
      }
      if (failed) {
        notifyWarning('Saved with warnings', `${failed} attachment(s) could not be stored. Open the entry to retry.`);
      }
      router.replace({ pathname: '/cases/[caseId]/narrations/[narrationId]', params: { caseId, narrationId: created.id } });
    } catch (e) {
      notifyError('Could not save', getApiErrorMessage(e, 'Please try again.'));
    } finally {
      setLoading(false);
    }
  };

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
      {loadError ? <Text style={styles.note}>{loadError} Connect once to download them, then this works offline.</Text> : null}
      <ChipSelect
        label="Activity"
        options={types.map(t => ({ value: t.id, label: t.name }))}
        selected={typeId}
        onChange={ids => ids[0] && setTypeId(ids)}
      />
      <Input label="What was done *" value={description} onChangeText={setDescription} multiline numberOfLines={4} style={[styles.multiline, styles.tall]} />

      {activity?.measure === 'minutes' ? (
        <>
          <Text style={styles.label}>Time spent{activity.increment > 1 ? ` (in steps of ${activity.increment} min)` : ''}</Text>
          <View style={styles.timeRow}>
            <View style={styles.timeCol}>
              <Input label="Hours" value={hours} onChangeText={setHours} keyboardType="number-pad" maxLength={2} />
            </View>
            <View style={styles.timeCol}>
              <Input label="Minutes" value={minutes} onChangeText={setMinutes} keyboardType="number-pad" maxLength={2} />
            </View>
          </View>
        </>
      ) : activity ? (
        <Input label={MEASURE_LABEL[activity.measure] ?? 'Amount'} value={amount} onChangeText={setAmount} keyboardType="number-pad" maxLength={5} />
      ) : null}

      {activity?.timeOnTop ? (
        <>
          <Input label="Time taken *" value={timeTaken} onChangeText={setTimeTaken} keyboardType="decimal-pad" maxLength={6} />
          <ChipSelect
            label="Time taken in"
            options={[
              { value: 'minutes', label: 'Minutes' },
              { value: 'hours', label: 'Hours' },
            ]}
            selected={timeUnit}
            onChange={setTimeUnit}
          />
        </>
      ) : null}

      <Input label="Date of the work" value={date} onChangeText={setDate} placeholder="YYYY-MM-DD" autoCapitalize="none" maxLength={10} />

      {hasFeature('documents') ? (
        <>
          <Text style={styles.section}>Documents</Text>
          {kinds.length > 0 ? (
            <ChipSelect
              label="Kind of document"
              options={kinds.map(k => ({ value: k.id, label: k.name }))}
              selected={kind.length > 0 ? kind : [kinds[0].id]}
              onChange={setKind}
            />
          ) : null}
          {files.map((f, i) => (
            <View key={`${f.uri}-${i}`} style={styles.fileRow}>
              <Text style={styles.fileName} numberOfLines={1}>{f.name}</Text>
              <Text style={styles.remove} onPress={() => setFiles(prev => prev.filter((_, idx) => idx !== i))}>Remove</Text>
            </View>
          ))}
          <Button label="+ Attach documents" variant="secondary" onPress={pickFiles} style={styles.attach} />
        </>
      ) : null}

      <Button label="Save entry" onPress={submit} loading={loading} disabled={!activity} />
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.background },
  content: { padding: spacing.lg, paddingBottom: spacing.xl * 2 },
  label: { ...typography.caption, color: colors.textSecondary, marginBottom: spacing.xs },
  section: { ...typography.h3, color: colors.navy, marginTop: spacing.md, marginBottom: spacing.sm },
  multiline: { minHeight: 80, textAlignVertical: 'top' },
  tall: { minHeight: 140 },
  timeRow: { flexDirection: 'row', gap: spacing.md },
  timeCol: { flex: 1 },
  switchRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: spacing.md },
  fileRow: { flexDirection: 'row', justifyContent: 'space-between', paddingVertical: spacing.xs },
  fileName: { ...typography.body, color: colors.textPrimary, flex: 1, marginRight: spacing.md },
  remove: { ...typography.caption, color: colors.danger, fontFamily: fontFamily.sansSemibold },
  attach: { marginVertical: spacing.sm },
  note: { ...typography.body, color: colors.textSecondary, padding: spacing.lg },
});
