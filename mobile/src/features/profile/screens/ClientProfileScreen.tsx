import React, { useCallback, useState } from 'react';
import { Alert, Linking, Pressable, ScrollView, StyleSheet, Switch, Text, View } from 'react-native';
import { useFocusEffect, useLocalSearchParams, useRouter } from 'expo-router';
import {
  addClientContact,
  fetchClient,
  recordClientConsent,
  updateClient,
  ClientChanges,
} from '@/api/endpoint/clients.api';
import { fetchCases } from '@/api/endpoint/cases.api';
import { getApiErrorMessage } from '@/api/errors';
import Button from '@/components/Button';
import Card from '@/components/Card';
import ChipSelect from '@/components/ChipSelect';
import DetailRow from '@/components/DetailRow';
import Input from '@/components/Input';
import LoadingSpinner from '@/components/LoadingSpinner';
import SectionHeader from '@/components/SectionHeader';
import { useAppSelector } from '@/hooks/useAppDispatch';
import { canEditRecords } from '@/constants/roles';
import { callLink, emailLink, smsLink, whatsAppLink } from '@/utils/contactLinks';
import { isValidEmail, isValidSAPhoneNumber } from '@/utils/validators';
import { visibleCasesFor } from '@/utils/caseAccess';
import { colors, radius, spacing, typography } from '@/theme/index';
import { Case, ClientContactType, ClientProfile, PopiaConsentType, RiskRating } from '@/models/index';

const RISK: RiskRating[] = ['Low', 'Medium', 'High'];
const CONTACT_TYPES: ClientContactType[] = ['Phone', 'Email', 'Fax', 'Physical Address'];
const CONSENTS: { type: PopiaConsentType; label: string }[] = [
  { type: 'DataProcessing', label: 'Processing of personal information' },
  { type: 'DocumentRetention', label: 'Document retention' },
  { type: 'ThirdPartySharing', label: 'Sharing with third parties' },
  { type: 'Marketing', label: 'Marketing communication' },
];
// Same rule the backend enforces for FICA status and risk rating.
const COMPLIANCE_ROLES = ['Admin', 'Director', 'CA'];

async function open(url: string, what: string) {
  try {
    await Linking.openURL(url);
  } catch {
    Alert.alert(`Cannot ${what}`, 'This phone has no app that can do that.');
  }
}

export default function ClientProfileScreen() {
  const router = useRouter();
  const { clientId } = useLocalSearchParams<{ clientId: string }>();
  const me = useAppSelector(state => state.auth.user);
  const [client, setClient] = useState<ClientProfile | null>(null);
  const [cases, setCases] = useState<Case[]>([]);
  const [offline, setOffline] = useState(false);
  const [loading, setLoading] = useState(true);
  const [failed, setFailed] = useState<string | null>(null);
  const [editing, setEditing] = useState(false);
  const [saving, setSaving] = useState(false);

  const [phone, setPhone] = useState('');
  const [email, setEmail] = useState('');
  const [address, setAddress] = useState('');
  const [notes, setNotes] = useState('');
  const [idNumber, setIdNumber] = useState('');

  const [contactType, setContactType] = useState<ClientContactType[]>(['Phone']);
  const [contactValue, setContactValue] = useState('');
  const [addingContact, setAddingContact] = useState(false);

  const load = useCallback(() => {
    Promise.all([fetchClient(clientId), fetchCases().catch(() => [] as Case[])])
      .then(([result, all]) => {
        setClient(result.client);
        setOffline(result.offline);
        setCases(visibleCasesFor(all, me).filter(c => c.clientId === clientId));
        setFailed(null);
      })
      .catch(e => setFailed(getApiErrorMessage(e, 'This client is not available.')))
      .finally(() => setLoading(false));
  }, [clientId, me]);

  // Reload whenever the screen is shown, so edits made elsewhere (and new cases) appear.
  useFocusEffect(
    useCallback(() => {
      load();
    }, [load]),
  );

  if (loading) {
    return <LoadingSpinner />;
  }
  if (!client) {
    return (
      <View style={styles.container}>
        <Text style={styles.note}>{failed ?? 'This client is not available.'}</Text>
      </View>
    );
  }

  const canEdit = me ? canEditRecords(me.role) && !offline : false;
  const canCompliance = me ? COMPLIANCE_ROLES.includes(me.role) && !offline : false;

  const save = async (changes: ClientChanges, success?: string) => {
    setSaving(true);
    try {
      setClient(await updateClient(client.id, changes));
      if (success) {
        Alert.alert('Saved', success);
      }
      return true;
    } catch (e) {
      Alert.alert('Could not save', getApiErrorMessage(e, 'Please try again.'));
      return false;
    } finally {
      setSaving(false);
    }
  };

  const startEdit = () => {
    setPhone(client.contactNumber ?? '');
    setEmail(client.email ?? '');
    setAddress(client.address ?? '');
    setNotes(client.notes ?? '');
    setIdNumber('');
    setEditing(true);
  };

  const submitEdit = async () => {
    if (phone.trim() && !isValidSAPhoneNumber(phone)) {
      Alert.alert('Check the phone number', 'Use a South African number, e.g. 082 123 4567 or +27 82 123 4567.');
      return;
    }
    if (email.trim() && !isValidEmail(email)) {
      Alert.alert('Check the e-mail address', 'That address does not look right.');
      return;
    }
    const changes: ClientChanges = { contactNumber: phone.trim(), email: email.trim(), address: address.trim(), notes: notes.trim() };
    if (idNumber.trim()) {
      changes.idNumber = idNumber.trim();
    }
    if (await save(changes)) {
      setEditing(false);
    }
  };

  const toggleConsent = (type: PopiaConsentType, label: string, granted: boolean) =>
    Alert.alert(
      granted ? 'Record consent' : 'Record withdrawal',
      `${granted ? 'The client has agreed to' : 'The client has withdrawn consent for'}: ${label.toLowerCase()}.`,
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Confirm',
          onPress: async () => {
            setSaving(true);
            try {
              setClient(await recordClientConsent(client.id, { consentType: type, granted }));
            } catch (e) {
              Alert.alert('Could not save', getApiErrorMessage(e, 'Please try again.'));
            } finally {
              setSaving(false);
            }
          },
        },
      ],
    );

  const addContact = async () => {
    if (!contactValue.trim()) {
      Alert.alert('Missing info', 'Enter the contact detail first.');
      return;
    }
    setAddingContact(true);
    try {
      setClient(await addClientContact(client.id, { type: contactType[0] ?? 'Phone', value: contactValue.trim() }));
      setContactValue('');
    } catch (e) {
      Alert.alert('Could not add contact', getApiErrorMessage(e, 'Please try again.'));
    } finally {
      setAddingContact(false);
    }
  };

  // The latest answer per consent type is what counts.
  const latestConsent = (type: PopiaConsentType) => (client.consents ?? []).find(c => c.consentType === type);

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
      <Text style={styles.title}>{client.fullName}</Text>
      {offline ? <Text style={styles.note}>Offline: showing what is saved on this phone. Editing needs a connection.</Text> : null}

      <View style={styles.actions}>
        <ActionButton label="Call" disabled={!client.contactNumber} onPress={() => open(callLink(client.contactNumber), 'call')} />
        <ActionButton label="SMS" disabled={!client.contactNumber} onPress={() => open(smsLink(client.contactNumber), 'send an SMS')} />
        <ActionButton label="WhatsApp" disabled={!client.contactNumber} onPress={() => open(whatsAppLink(client.contactNumber), 'open WhatsApp')} />
        <ActionButton label="E-mail" disabled={!client.email} onPress={() => open(emailLink(client.email), 'send an e-mail')} />
      </View>

      {editing ? (
        <Card style={styles.card}>
          <Text style={styles.cardTitle}>Edit details</Text>
          <Input label="Phone" value={phone} onChangeText={setPhone} keyboardType="phone-pad" placeholder="082 123 4567" />
          <Input label="E-mail" value={email} onChangeText={setEmail} keyboardType="email-address" autoCapitalize="none" />
          <Input label="Address" value={address} onChangeText={setAddress} multiline style={styles.multiline} />
          <Input
            label={client.idNumberMasked ? `ID number (saved: ${client.idNumberMasked}) — leave blank to keep` : 'ID number'}
            value={idNumber}
            onChangeText={setIdNumber}
            keyboardType="number-pad"
            maxLength={13}
          />
          <Input label="Notes" value={notes} onChangeText={setNotes} multiline style={styles.multiline} />
          <Button label="Save details" onPress={submitEdit} loading={saving} />
          <Button label="Cancel" variant="secondary" onPress={() => setEditing(false)} style={styles.gap} />
        </Card>
      ) : (
        <Card style={styles.card}>
          <DetailRow label="Phone" value={client.contactNumber} />
          <DetailRow label="E-mail" value={client.email} />
          <DetailRow label="Address" value={client.address} />
          <DetailRow label="ID number" value={client.idNumberMasked} />
          <DetailRow label="Notes" value={client.notes} />
          {!client.contactNumber && !client.email && !client.address ? (
            <Text style={styles.muted}>No contact details recorded yet.</Text>
          ) : null}
          {canEdit ? <Button label="Edit details" variant="secondary" onPress={startEdit} style={styles.gap} /> : null}
        </Card>
      )}

      <SectionHeader title="FICA & risk" />
      <Card style={styles.card}>
        <View style={styles.row}>
          <View style={styles.flex}>
            <Text style={styles.cardTitle}>FICA verified</Text>
            <Text style={styles.muted}>
              {client.ficaVerified ? `Verified${client.ficaVerifiedAt ? ` on ${client.ficaVerifiedAt.slice(0, 10)}` : ''}` : 'Identity documents not yet verified'}
            </Text>
          </View>
          <Switch
            accessibilityLabel="FICA verified"
            value={client.ficaVerified}
            disabled={!canCompliance || saving}
            onValueChange={v => {
              save({ ficaVerified: v });
            }}
          />
        </View>
        <ChipSelect
          label="Risk rating"
          options={RISK.map(r => ({ value: r, label: r }))}
          selected={[client.riskRating]}
          onChange={([r]) => {
            if (r && canCompliance && r !== client.riskRating) {
              save({ riskRating: r });
            }
          }}
        />
        {!canCompliance && !offline ? <Text style={styles.muted}>Only a CA, Director or Admin can change these.</Text> : null}
      </Card>

      <SectionHeader title="POPIA consent" />
      <Card style={styles.card}>
        {CONSENTS.map(({ type, label }) => {
          const latest = latestConsent(type);
          return (
            <View key={type} style={styles.consentRow}>
              <View style={styles.flex}>
                <Text style={styles.consentLabel}>{label}</Text>
                <Text style={styles.muted}>
                  {latest ? `${latest.granted ? 'Granted' : 'Withdrawn'} on ${latest.recordedAt.slice(0, 10)}` : 'Not recorded'}
                </Text>
              </View>
              {canEdit ? (
                <Switch
                  accessibilityLabel={label}
                  value={Boolean(latest?.granted)}
                  disabled={saving}
                  onValueChange={v => toggleConsent(type, label, v)}
                />
              ) : null}
            </View>
          );
        })}
      </Card>

      <SectionHeader title="Other contacts" />
      {(client.contacts ?? []).length === 0 ? <Text style={styles.muted}>No additional contacts.</Text> : null}
      {(client.contacts ?? []).map(c => (
        <Card key={c.id}>
          <Text style={styles.consentLabel}>
            {c.type}
            {c.isPrimary ? ' · primary' : ''}
          </Text>
          <Text style={styles.body}>{c.value}</Text>
        </Card>
      ))}
      {canEdit ? (
        <Card style={styles.card}>
          <ChipSelect label="Add a contact" options={CONTACT_TYPES.map(t => ({ value: t, label: t }))} selected={contactType} onChange={setContactType} />
          <Input label="Detail" value={contactValue} onChangeText={setContactValue} autoCapitalize="none" />
          <Button label="+ Add contact" variant="secondary" onPress={addContact} loading={addingContact} />
        </Card>
      ) : null}

      <SectionHeader title={`Cases (${cases.length})`} />
      {cases.length === 0 ? <Text style={styles.muted}>No cases you can see for this client.</Text> : null}
      {cases.map(c => (
        <Pressable key={c.id} onPress={() => router.push({ pathname: '/cases/[caseId]', params: { caseId: c.id } })}>
          <Card>
            <Text style={styles.consentLabel}>{c.fileReference}</Text>
            <Text style={styles.body}>
              {c.matterType} · {c.status}
            </Text>
          </Card>
        </Pressable>
      ))}
      {canEditRecords(me?.role ?? 'Messenger') ? (
        <Button
          label="+ New case for this client"
          onPress={() => router.push({ pathname: '/cases/new', params: { clientName: client.fullName } })}
          style={styles.gap}
        />
      ) : null}
    </ScrollView>
  );
}

function ActionButton({ label, onPress, disabled }: { label: string; onPress: () => void; disabled?: boolean }) {
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityState={{ disabled: Boolean(disabled) }}
      disabled={disabled}
      onPress={onPress}
      style={[styles.action, disabled && styles.actionDisabled]}
    >
      <Text style={[styles.actionText, disabled && styles.actionTextDisabled]}>{label}</Text>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.background },
  content: { padding: spacing.md, paddingBottom: spacing.xl * 2 },
  title: { ...typography.h1, color: colors.navy, marginBottom: spacing.sm },
  note: { ...typography.caption, color: colors.textSecondary, marginBottom: spacing.sm },
  muted: { ...typography.caption, color: colors.textSecondary },
  body: { ...typography.body, color: colors.textPrimary },
  card: { marginTop: spacing.sm },
  cardTitle: { ...typography.body, fontWeight: '700', color: colors.textPrimary },
  actions: { flexDirection: 'row', gap: spacing.sm, marginVertical: spacing.sm },
  action: {
    flex: 1,
    alignItems: 'center',
    paddingVertical: spacing.sm + 2,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.navy,
    backgroundColor: colors.surface,
  },
  actionDisabled: { borderColor: colors.border, opacity: 0.5 },
  actionText: { ...typography.caption, fontWeight: '700', color: colors.navy },
  actionTextDisabled: { color: colors.textSecondary },
  row: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: spacing.md, marginBottom: spacing.md },
  flex: { flex: 1 },
  consentRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: spacing.md, paddingVertical: spacing.xs },
  consentLabel: { ...typography.body, fontWeight: '600', color: colors.textPrimary },
  multiline: { minHeight: 70, textAlignVertical: 'top' },
  gap: { marginTop: spacing.sm },
});
