import React, { useState } from 'react';
import { Alert, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useRouter } from 'expo-router';
import Button from '@/components/Button';
import Input from '@/components/Input';
import ChipSelect from '@/components/ChipSelect';
import { createCase } from '@/api/endpoint/cases.api';
import { getApiErrorMessage } from '@/api/errors';
import { useAppSelector } from '@/hooks/useAppDispatch';
import { useAssignableUsers } from '@/hooks/useAssignableUsers';
import { canEditRecords } from '@/constants/roles';
import { isValidIsoDate } from '@/utils/validators';
import { colors, spacing, typography } from '@/theme/index';
import { CasePriority } from '@/models/index';

const PRIORITIES: CasePriority[] = ['Low', 'Medium', 'High', 'Urgent'];

interface CustomField {
  key: string;
  value: string;
}

export default function NewCaseScreen() {
  const router = useRouter();
  const me = useAppSelector(state => state.auth.user);
  const users = useAssignableUsers();

  const [clientName, setClientName] = useState('');
  const [matterType, setMatterType] = useState('');
  const [practiceArea, setPracticeArea] = useState('');
  const [issueDescription, setIssueDescription] = useState('');
  const [courtCaseNumber, setCourtCaseNumber] = useState('');
  const [jurisdiction, setJurisdiction] = useState('');
  const [opposingParty, setOpposingParty] = useState('');
  const [opposingAttorney, setOpposingAttorney] = useState('');
  const [openedDate, setOpenedDate] = useState(new Date().toISOString().slice(0, 10));
  const [nextCourtDate, setNextCourtDate] = useState('');
  const [estimatedValue, setEstimatedValue] = useState('');
  const [notes, setNotes] = useState('');
  const [priority, setPriority] = useState<CasePriority[]>(['Medium']);
  const [assignedUserIds, setAssignedUserIds] = useState<string[]>(me ? [me.id] : []);
  const [customFields, setCustomFields] = useState<CustomField[]>([]);
  const [loading, setLoading] = useState(false);

  if (me && !canEditRecords(me.role)) {
    return (
      <View style={styles.container}>
        <Text style={styles.note}>Your role can view cases but not create them.</Text>
      </View>
    );
  }

  const updateCustom = (index: number, patch: Partial<CustomField>) =>
    setCustomFields(fields => fields.map((f, i) => (i === index ? { ...f, ...patch } : f)));

  const submit = async () => {
    if (!clientName.trim() || !matterType.trim() || !issueDescription.trim()) {
      Alert.alert('Missing info', 'Client name, matter type and a description of the issue are required.');
      return;
    }
    if (!isValidIsoDate(openedDate) || (nextCourtDate && !isValidIsoDate(nextCourtDate))) {
      Alert.alert('Invalid date', 'Use the format YYYY-MM-DD (e.g. 2026-11-05).');
      return;
    }
    const value = estimatedValue ? Number(estimatedValue.replace(/[\s,]/g, '')) : undefined;
    if (value !== undefined && Number.isNaN(value)) {
      Alert.alert('Invalid amount', 'Estimated value must be a number.');
      return;
    }

    setLoading(true);
    try {
      const custom = Object.fromEntries(
        customFields.filter(f => f.key.trim() && f.value.trim()).map(f => [f.key.trim(), f.value.trim()]),
      );
      const created = await createCase({
        clientName: clientName.trim(),
        matterType: matterType.trim(),
        practiceArea: practiceArea.trim() || undefined,
        issueDescription: issueDescription.trim(),
        courtCaseNumber: courtCaseNumber.trim() || undefined,
        jurisdiction: jurisdiction.trim() || undefined,
        opposingParty: opposingParty.trim() || undefined,
        opposingAttorney: opposingAttorney.trim() || undefined,
        openedDate,
        nextCourtDate: nextCourtDate || undefined,
        estimatedValue: value,
        notes: notes.trim() || undefined,
        priority: priority[0] ?? 'Medium',
        leadAttorneyId: me?.id,
        assignedUserIds,
        customFields: Object.keys(custom).length ? custom : undefined,
      });
      router.replace({ pathname: '/cases/[caseId]', params: { caseId: created.id } });
    } catch (e) {
      Alert.alert('Could not create case', getApiErrorMessage(e, 'Please try again.'));
    } finally {
      setLoading(false);
    }
  };

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
      <Text style={styles.section}>Client & matter</Text>
      <Input label="Client name *" value={clientName} onChangeText={setClientName} />
      <Input label="Matter type *" value={matterType} onChangeText={setMatterType} placeholder="e.g. Litigation" />
      <Input label="Practice area" value={practiceArea} onChangeText={setPracticeArea} placeholder="e.g. Labour, Property" />
      <Input
        label="Issue / matter description *"
        value={issueDescription}
        onChangeText={setIssueDescription}
        multiline
        numberOfLines={4}
        style={styles.multiline}
      />
      <ChipSelect label="Priority" options={PRIORITIES.map(p => ({ value: p, label: p }))} selected={priority} onChange={setPriority} />

      <Text style={styles.section}>Court & parties</Text>
      <Input label="Court case number" value={courtCaseNumber} onChangeText={setCourtCaseNumber} />
      <Input label="Jurisdiction / court" value={jurisdiction} onChangeText={setJurisdiction} />
      <Input label="Opposing party" value={opposingParty} onChangeText={setOpposingParty} />
      <Input label="Opposing attorney" value={opposingAttorney} onChangeText={setOpposingAttorney} />

      <Text style={styles.section}>Dates & value</Text>
      <Input label="Opened (YYYY-MM-DD)" value={openedDate} onChangeText={setOpenedDate} autoCapitalize="none" />
      <Input label="Next court date (YYYY-MM-DD)" value={nextCourtDate} onChangeText={setNextCourtDate} autoCapitalize="none" />
      <Input label="Estimated value (R)" value={estimatedValue} onChangeText={setEstimatedValue} keyboardType="decimal-pad" />

      <Text style={styles.section}>Team</Text>
      <ChipSelect
        label="Assigned users"
        multiple
        options={users.map(u => ({ value: u.id, label: u.fullName }))}
        selected={assignedUserIds}
        onChange={setAssignedUserIds}
      />

      <Text style={styles.section}>Custom fields</Text>
      {customFields.map((field, index) => (
        <View key={field.key} style={styles.customRow}>
          <View style={styles.customCol}>
            <Input label="Name" value={field.key} onChangeText={v => updateCustom(index, { key: v })} />
          </View>
          <View style={styles.customCol}>
            <Input label="Value" value={field.value} onChangeText={v => updateCustom(index, { value: v })} />
          </View>
        </View>
      ))}
      <Button
        label="+ Add custom field"
        variant="secondary"
        onPress={() => setCustomFields(f => [...f, { key: '', value: '' }])}
        style={styles.addField}
      />

      <Input label="Notes" value={notes} onChangeText={setNotes} multiline numberOfLines={3} style={styles.multiline} />
      <Button label="Create case" onPress={submit} loading={loading} />
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.background },
  content: { padding: spacing.lg, paddingBottom: spacing.xl * 2 },
  section: { ...typography.h3, color: colors.navy, marginTop: spacing.md, marginBottom: spacing.sm },
  multiline: { minHeight: 90, textAlignVertical: 'top' },
  customRow: { flexDirection: 'row', gap: spacing.sm },
  customCol: { flex: 1 },
  addField: { marginBottom: spacing.md },
  note: { ...typography.body, color: colors.textSecondary, padding: spacing.lg },
});
