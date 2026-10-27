import React, { useEffect, useState } from 'react';
import { Alert, ScrollView, StyleSheet, Switch, Text, View } from 'react-native';
import { useLocalSearchParams, useRouter } from 'expo-router';
import * as DocumentPicker from 'expo-document-picker';
import Button from '@/components/Button';
import Input from '@/components/Input';
import ChipSelect from '@/components/ChipSelect';
import { addNarration } from '@/api/endpoint/narrations.api';
import { getApiErrorMessage } from '@/api/errors';
import { fetchDiary } from '@/api/endpoint/darzations.api';
import { attachDocumentToNarration, PickedDocumentFile } from '@/features/documents/services/narrationDocuments';
import { useAppSelector } from '@/hooks/useAppDispatch';
import { canEditRecords } from '@/constants/roles';
import { colors, spacing, typography } from '@/theme/index';
import { Darzation, DocumentConfidentiality, Narration } from '@/models/index';

const ACTIVITY_TYPES: Narration['activityType'][] = ['Call', 'Draft', 'Appearance', 'Travel', 'Research', 'Meeting', 'Other'];
const CONFIDENTIALITY: DocumentConfidentiality[] = ['Standard', 'Restricted', 'Privileged'];
const MAX_FILE_BYTES = 25 * 1024 * 1024; // 25 MB per attachment keeps on-device storage sensible

export default function AddNarrationScreen() {
  const router = useRouter();
  const { caseId, darzationId: presetDarzationId } = useLocalSearchParams<{ caseId: string; darzationId?: string }>();
  const me = useAppSelector(state => state.auth.user);

  const [activityType, setActivityType] = useState<Narration['activityType'][]>(['Call']);
  const [title, setTitle] = useState('');
  const [description, setDescription] = useState('');
  const [detailedNotes, setDetailedNotes] = useState('');
  const [hours, setHours] = useState('0');
  const [minutes, setMinutes] = useState('0');
  const [billable, setBillable] = useState(true);
  const [diary, setDiary] = useState<Darzation[]>([]);
  const [darzationId, setDarzationId] = useState<string[]>(presetDarzationId ? [presetDarzationId] : []);
  const [files, setFiles] = useState<PickedDocumentFile[]>([]);
  const [confidentiality, setConfidentiality] = useState<DocumentConfidentiality[]>(['Standard']);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    fetchDiary()
      .then(all => setDiary(all.filter(d => d.caseId === caseId)))
      .catch(() => setDiary([]));
  }, [caseId]);

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
      if (asset.size && asset.size > MAX_FILE_BYTES) {
        Alert.alert('File too large', `${asset.name} is over 25 MB and was skipped.`);
        continue;
      }
      accepted.push({ uri: asset.uri, name: asset.name, mimeType: asset.mimeType, size: asset.size });
    }
    setFiles(prev => [...prev, ...accepted]);
  };

  const submit = async () => {
    const timeSpentMinutes = (Number.parseInt(hours, 10) || 0) * 60 + (Number.parseInt(minutes, 10) || 0);
    if (!description.trim()) {
      Alert.alert('Add a description', 'A short note on what was done is required.');
      return;
    }
    if (timeSpentMinutes < 0 || timeSpentMinutes > 24 * 60) {
      Alert.alert('Check the time', 'Time spent must be between 0 and 24 hours.');
      return;
    }
    setLoading(true);
    try {
      const created = await addNarration({
        caseId,
        darzationId: darzationId[0],
        authorId: me?.id ?? '',
        activityType: activityType[0] ?? 'Other',
        title: title.trim() || undefined,
        description: description.trim(),
        detailedNotes: detailedNotes.trim() || undefined,
        timeSpentMinutes,
        billable,
      });

      const uploadResults = await Promise.all(
        files.map(async file => {
          try {
            await attachDocumentToNarration({
              caseId,
              narrationId: created.id,
              file,
              confidentiality: confidentiality[0] ?? 'Standard',
              uploadedById: me?.id ?? '',
            });

            return true;
          } catch (error) {
            console.error('Failed upload:', error);
            return false;
          }
        }),
      );

      const failed = uploadResults.filter(success => !success).length;

      if (failed > 0) {
        Alert.alert(
          'Saved with warnings',
          `${failed} attachment(s) could not be stored. Open the entry to retry.`,
        );
      }

      router.replace({ pathname: '/cases/[caseId]/narrations/[narrationId]', params: { caseId, narrationId: created.id } });
    } catch (e) {
      Alert.alert('Could not save', getApiErrorMessage(e, 'Please try again.'));
    } finally {
      setLoading(false);
    }
  };

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
      <ChipSelect label="Activity type" options={ACTIVITY_TYPES.map(t => ({ value: t, label: t }))} selected={activityType} onChange={setActivityType} />
      <Input label="Title" value={title} onChangeText={setTitle} placeholder="Short headline (optional)" />
      <Input label="Summary *" value={description} onChangeText={setDescription} multiline numberOfLines={3} style={styles.multiline} />
      <Input label="Detailed notes" value={detailedNotes} onChangeText={setDetailedNotes} multiline numberOfLines={6} style={[styles.multiline, styles.tall]} />

      <Text style={styles.label}>Time spent</Text>
      <View style={styles.timeRow}>
        <View style={styles.timeCol}>
          <Input label="Hours" value={hours} onChangeText={setHours} keyboardType="number-pad" maxLength={2} />
        </View>
        <View style={styles.timeCol}>
          <Input label="Minutes" value={minutes} onChangeText={setMinutes} keyboardType="number-pad" maxLength={2} />
        </View>
      </View>

      {diary.length ? (
        <ChipSelect
          label="Linked diary item (optional)"
          options={diary.map(d => ({ value: d.id, label: d.title || d.nextAction }))}
          selected={darzationId}
          onChange={(ids: any[]) => setDarzationId(prev => (ids[0] && prev[0] === ids[0] ? [] : ids))}
        />
      ) : null}

      <View style={styles.switchRow}>
        <Text style={styles.label}>Billable</Text>
        <Switch value={billable} onValueChange={setBillable} />
      </View>

      <Text style={styles.section}>Documents</Text>
      <ChipSelect
        label="Confidentiality for these files"
        options={CONFIDENTIALITY.map(c => ({ value: c, label: c }))}
        selected={confidentiality}
        onChange={setConfidentiality}
      />
      {files.map((f, i) => (
        <View key={`${f.uri}-${i}`} style={styles.fileRow}>
          <Text style={styles.fileName} numberOfLines={1}>{f.name}</Text>
          <Text style={styles.remove} onPress={() => setFiles(prev => prev.filter((_, idx) => idx !== i))}>Remove</Text>
        </View>
      ))}
      <Button label="+ Attach documents" variant="secondary" onPress={pickFiles} style={styles.attach} />

      <Button label="Save entry" onPress={submit} loading={loading} />
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
  remove: { ...typography.caption, color: colors.danger, fontWeight: '600' },
  attach: { marginVertical: spacing.sm },
  note: { ...typography.body, color: colors.textSecondary, padding: spacing.lg },
});
