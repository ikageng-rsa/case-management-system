import React, { useCallback, useEffect, useRef, useState } from 'react';
import { Linking, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useFocusEffect, useLocalSearchParams, useRouter } from 'expo-router';
import { fetchClient, revealClientField } from '@/api/endpoint/clients.api';
import { fetchCases } from '@/api/endpoint/cases.api';
import { getApiErrorMessage } from '@/api/errors';
import Card from '@/components/Card';
import DetailRow from '@/components/DetailRow';
import LoadingSpinner from '@/components/LoadingSpinner';
import SectionHeader from '@/components/SectionHeader';
import { useAppSelector } from '@/hooks/useAppDispatch';
import { deviceAuthAvailable, requireDeviceAuth } from '@/features/auth/services/deviceAuth';
import { callLink, emailLink, smsLink, whatsAppLink } from '@/utils/contactLinks';
import { visibleCasesFor } from '@/utils/caseAccess';
import { isNetworkError } from '@/offline/connectivity';
import { colors, fontFamily, radius, spacing, typography } from '@/theme/index';
import { Case, ClientContact, ClientProfile } from '@/models/index';
import { notifyError } from '@/utils/notify';

/** A revealed value is shown for this long, then masked again. It only ever lives in this screen's state. */
const REVEAL_SECONDS = 30;

const KIND_LABEL: Record<string, string> = {
  email: 'E-mail',
  mobile: 'Mobile',
  phone: 'Phone',
  fax: 'Fax',
  'physical-address': 'Address',
  'postal-address': 'Postal address',
};
const kindLabel = (kind: string) => KIND_LABEL[kind] ?? kind.replace(/[-_]/g, ' ').replace(/^\w/, c => c.toUpperCase());

async function open(url: string, what: string) {
  try {
    await Linking.openURL(url);
  } catch {
    notifyError(`Cannot ${what}`, 'This phone has no app that can do that.');
  }
}

type RevealTarget = 'identifier' | `contact:${string}`;

export default function ClientProfileScreen() {
  const router = useRouter();
  const { clientId } = useLocalSearchParams<{ clientId: string }>();
  const me = useAppSelector(state => state.auth.user);
  const [client, setClient] = useState<ClientProfile | null>(null);
  const [cases, setCases] = useState<Case[]>([]);
  const [loading, setLoading] = useState(true);
  const [failed, setFailed] = useState<string | null>(null);

  // Real values, by target. Kept only in this component's state and wiped by the timers below / on leaving.
  const [revealed, setRevealed] = useState<Record<string, string>>({});
  const [busy, setBusy] = useState<string | null>(null);
  const timers = useRef<Record<string, ReturnType<typeof setTimeout>>>({});

  const hide = useCallback((target: string) => {
    clearTimeout(timers.current[target]);
    delete timers.current[target];
    setRevealed(prev => {
      const next = { ...prev };
      delete next[target];
      return next;
    });
  }, []);

  const hideAll = useCallback(() => {
    Object.values(timers.current).forEach(clearTimeout);
    timers.current = {};
    setRevealed({});
  }, []);

  useEffect(() => hideAll, [hideAll]);

  const load = useCallback(() => {
    Promise.all([fetchClient(clientId), fetchCases().catch(() => [] as Case[])])
      .then(([result, all]) => {
        setClient(result);
        setCases(visibleCasesFor(all, me).filter(c => c.clientId === clientId));
        setFailed(null);
      })
      .catch(e => {
        if (isNetworkError(e)) {
          // Offline: the masked client details are not on this phone, only the matters are.
          fetchCases()
            .then(all => {
              const mine = visibleCasesFor(all, me).filter(c => c.clientId === clientId);
              if (mine.length) {
                setCases(mine);
                setClient({ id: clientId, fullName: mine[0].clientName, clientType: 'individual' });
                setFailed(null);
              } else {
                setFailed('Connect to the internet to open this client.');
              }
            })
            .catch(() => setFailed('Connect to the internet to open this client.'));
        } else {
          setFailed(getApiErrorMessage(e, 'This client is not available.'));
        }
      })
      .finally(() => setLoading(false));
  }, [clientId, me]);

  // Reload whenever the screen is shown, and mask everything again when leaving it.
  useFocusEffect(
    useCallback(() => {
      load();
      return hideAll;
    }, [load, hideAll]),
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

  const canReveal = client.can?.reveal === true;
  const canSeeContacts = client.can?.view_contacts !== false;

  const reveal = async (target: RevealTarget) => {
    if (revealed[target] !== undefined) {
      hide(target);
      return;
    }
    // Showing personal information is a protected action: the phone's owner must pass the lock first.
    if (await deviceAuthAvailable()) {
      if (!(await requireDeviceAuth('Confirm it is you to show this detail'))) {
        return;
      }
    }
    setBusy(target);
    try {
      const value = await revealClientField(client.id, target);
      setRevealed(prev => ({ ...prev, [target]: value }));
      clearTimeout(timers.current[target]);
      timers.current[target] = setTimeout(() => hide(target), REVEAL_SECONDS * 1000);
    } catch (e) {
      notifyError('Could not show this detail', isNetworkError(e) ? 'Connect to the internet and try again.' : getApiErrorMessage(e, 'Please try again.'));
    } finally {
      setBusy(null);
    }
  };

  const contacts = client.contacts ?? [];
  const contactValue = (c: ClientContact) => revealed[`contact:${c.id}`];

  const quickAction = (c: ClientContact) => {
    const value = contactValue(c);
    if (!value) {
      return null;
    }
    if (c.kind === 'email') {
      return [{ label: 'E-mail', run: () => open(emailLink(value), 'send an e-mail') }];
    }
    if (c.kind === 'mobile' || c.kind === 'phone') {
      return [
        { label: 'Call', run: () => open(callLink(value), 'call') },
        { label: 'SMS', run: () => open(smsLink(value), 'send an SMS') },
        { label: 'WhatsApp', run: () => open(whatsAppLink(value), 'open WhatsApp') },
      ];
    }
    return null;
  };

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content}>
      <Text style={styles.title}>{client.fullName}</Text>
      {failed ? <Text style={styles.note}>{failed}</Text> : null}

      <Card style={styles.card}>
        <DetailRow label="Type" value={client.clientType === 'entity' ? 'Company / entity' : 'Individual'} />
        {client.identityType ? <DetailRow label="ID type" value={client.identityType.replace(/_/g, ' ')} /> : null}
        {client.identifierMasked ? (
          <View style={styles.revealRow}>
            <View style={styles.flex}>
              <DetailRow label={client.clientType === 'entity' ? 'Registration no.' : 'ID number'} value={revealed.identifier ?? client.identifierMasked} />
            </View>
            {canReveal ? (
              <RevealButton shown={revealed.identifier !== undefined} busy={busy === 'identifier'} onPress={() => reveal('identifier')} />
            ) : null}
          </View>
        ) : null}
        {client.dateOfBirthMasked ? <DetailRow label="Date of birth" value={client.dateOfBirthMasked} /> : null}
        {client.mattersCount !== undefined ? <DetailRow label="Matters" value={String(client.mattersCount)} /> : null}
      </Card>
      {canReveal ? (
        <Text style={styles.note}>
          Details are hidden by default. Showing one asks for your fingerprint, face or PIN, is recorded by the firm&apos;s
          system, and hides again after {REVEAL_SECONDS} seconds.
        </Text>
      ) : null}

      <SectionHeader title="Contact details" />
      {!canSeeContacts ? <Text style={styles.muted}>You don&apos;t have permission to see this client&apos;s contact details.</Text> : null}
      {canSeeContacts && contacts.length === 0 ? <Text style={styles.muted}>No contact details recorded.</Text> : null}
      {canSeeContacts &&
        contacts.map(c => {
          const target = `contact:${c.id}` as const;
          const actions = quickAction(c);
          return (
            <Card key={c.id}>
              <View style={styles.revealRow}>
                <View style={styles.flex}>
                  <Text style={styles.consentLabel}>
                    {kindLabel(c.kind)}
                    {c.isPrimary ? ' · primary' : ''}
                  </Text>
                  <Text selectable={revealed[target] !== undefined} style={styles.body}>
                    {revealed[target] ?? c.valueMasked}
                  </Text>
                </View>
                {canReveal ? <RevealButton shown={revealed[target] !== undefined} busy={busy === target} onPress={() => reveal(target)} /> : null}
              </View>
              {actions ? (
                <View style={styles.actions}>
                  {actions.map(a => (
                    <Pressable key={a.label} accessibilityRole="button" onPress={a.run} style={styles.action}>
                      <Text style={styles.actionText}>{a.label}</Text>
                    </Pressable>
                  ))}
                </View>
              ) : null}
            </Card>
          );
        })}

      <SectionHeader title={`Matters (${cases.length})`} />
      {cases.length === 0 ? <Text style={styles.muted}>No matters you can see for this client.</Text> : null}
      {cases.map(c => (
        <Pressable key={c.id} onPress={() => router.push({ pathname: '/cases/[caseId]', params: { caseId: c.id } })}>
          <Card>
            <Text style={styles.consentLabel}>{c.fileReference}</Text>
            <Text style={styles.body}>{c.title || c.matterType}</Text>
            <Text style={styles.muted}>
              {c.matterType} · {c.status}
            </Text>
          </Card>
        </Pressable>
      ))}
    </ScrollView>
  );
}

function RevealButton({ shown, busy, onPress }: { shown: boolean; busy: boolean; onPress: () => void }) {
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={shown ? 'Hide detail' : 'Show detail'}
      disabled={busy}
      onPress={onPress}
      style={[styles.reveal, busy && styles.revealBusy]}
    >
      <Text style={styles.revealText}>{busy ? '…' : shown ? 'Hide' : 'Show'}</Text>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.background },
  content: { padding: spacing.md, paddingBottom: spacing.xl * 2 },
  title: { ...typography.h1, color: colors.navy, marginBottom: spacing.sm },
  note: { ...typography.caption, color: colors.textSecondary, marginVertical: spacing.sm },
  muted: { ...typography.caption, color: colors.textSecondary },
  body: { ...typography.body, color: colors.textPrimary },
  card: { marginTop: spacing.sm },
  actions: { flexDirection: 'row', gap: spacing.sm, marginTop: spacing.sm },
  action: {
    flex: 1,
    alignItems: 'center',
    paddingVertical: spacing.sm,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.navy,
    backgroundColor: colors.surface,
  },
  actionText: { ...typography.caption, fontFamily: fontFamily.sansSemibold, color: colors.navy },
  revealRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: spacing.md },
  flex: { flex: 1 },
  reveal: {
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.xs + 2,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.navy,
  },
  revealBusy: { opacity: 0.5 },
  revealText: { ...typography.caption, fontFamily: fontFamily.sansSemibold, color: colors.navy },
  consentLabel: { ...typography.body, fontFamily: fontFamily.sansSemibold, color: colors.textPrimary },
});
