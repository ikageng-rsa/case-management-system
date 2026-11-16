import React, { useCallback, useState } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useFocusEffect, useLocalSearchParams, useRouter } from 'expo-router';
import { fetchCaseById } from '@/api/endpoint/cases.api';
import { fetchMatterDiary } from '@/api/endpoint/darzations.api';
import { assignToMatter, fetchCapacities, fetchParties, removeAssignment, AssignmentCapacity, MatterParty } from '@/api/endpoint/assignments.api';
import { getApiErrorMessage } from '@/api/errors';
import { hasFeature } from '@/config/features';
import ChipSelect from '@/components/ChipSelect';
import { isNetworkError } from '@/offline/connectivity';
import Card from '@/components/Card';
import Button from '@/components/Button';
import DetailRow from '@/components/DetailRow';
import PriorityBadge from '@/components/PriorityBadge';
import SyncBadge from '@/components/SyncBadge';
import { useOnSynced } from '@/hooks/useSyncStatus';
import LoadingSpinner from '@/components/LoadingSpinner';
import SectionHeader from '@/components/SectionHeader';
import CaseStatusBadge from '@/features/cases/components/CaseStatusBadge';
import { useAppSelector } from '@/hooks/useAppDispatch';
import { useAssignableUsers } from '@/hooks/useAssignableUsers';
import { canAssignOn, canSeeCase, canWorkOn } from '@/utils/caseAccess';
import { formatDue } from '@/utils/dueAt';
import { colors, fontFamily, spacing, typography } from '@/theme/index';
import { Case, Darzation } from '@/models/index';
import { notifyError } from '@/utils/notify';
import { confirmAction } from '@/ui';

export default function CaseDetailScreen() {
  const router = useRouter();
  const { caseId } = useLocalSearchParams<{ caseId: string }>();
  const me = useAppSelector(state => state.auth.user);
  const users = useAssignableUsers();
  const [item, setItem] = useState<Case | null>(null);
  const [diary, setDiary] = useState<Darzation[]>([]);
  const [loading, setLoading] = useState(true);
  const [parties, setParties] = useState<MatterParty[]>([]);
  const [capacities, setCapacities] = useState<AssignmentCapacity[]>([]);
  const [pickUser, setPickUser] = useState<string[]>([]);
  const [pickCapacity, setPickCapacity] = useState<string[]>(['assisting']);
  const [busy, setBusy] = useState(false);

  const load = useCallback(() => {
    Promise.all([fetchCaseById(caseId), fetchMatterDiary(caseId).catch(() => [] as Darzation[])])
      .then(([c, d]) => {
        setItem(c);
        setDiary(d);
        // Online extras: failing quietly leaves the saved copy on screen.
        fetchParties(caseId).then(setParties).catch(() => { });
        if (c.can?.assign) {
          fetchCapacities().then(setCapacities).catch(() => { });
        }
      })
      .catch(() => setItem(null))
      .finally(() => setLoading(false));
  }, [caseId]);

  // Reload whenever the screen is shown again (e.g. back from the edit form).
  useFocusEffect(
    useCallback(() => {
      load();
    }, [load]),
  );
  // Once offline work has synced, the case has its real id and file reference.
  useOnSynced(load);

  if (loading) {
    return <LoadingSpinner />;
  }
  if (!item || !canSeeCase(item, me)) {
    return (
      <View style={styles.container}>
        <Text style={styles.note}>This case is not available to you.</Text>
      </View>
    );
  }

  const nameOf = (id: string) => users.find(u => u.id === id)?.fullName ?? item.assignments?.find(a => a.userId === id)?.userName ?? id;
  const canAssign = canAssignOn(item);

  const run = async (task: () => Promise<unknown>) => {
    setBusy(true);
    try {
      await task();
      setPickUser([]);
      load();
    } catch (e) {
      notifyError('Could not update', isNetworkError(e) ? 'Connect to the internet and try again.' : getApiErrorMessage(e, 'Please try again.'));
    } finally {
      setBusy(false);
    }
  };

  const assign = () => {
    if (!pickUser[0] || !pickCapacity[0]) {
      notifyError('Choose a person', 'Pick who to assign and in what capacity.');
      return;
    }
    run(() => assignToMatter(item.id, pickUser[0], pickCapacity[0]));
  };

  const unassign = async (assignmentId: string, name: string) => {
    const ok = await confirmAction({ title: 'Remove assignment?', message: name, actionLabel: 'Remove', icon: 'user-minus' });
    if (ok) {
      run(() => removeAssignment(item.id, assignmentId));
    }
  };

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content}>
      <View style={styles.headerRow}>
        <Text style={styles.fileRef}>{item.fileReference}</Text>
        <CaseStatusBadge status={item.status} />
      </View>
      <Text style={styles.client}>{item.title || item.clientName}</Text>
      {item.title ? <Text style={styles.body}>{item.clientName}</Text> : null}
      {item.clientId ? (
        <Pressable onPress={() => router.push({ pathname: '/more/client/[clientId]', params: { clientId: item.clientId } })}>
          <Text style={styles.link}>View client profile →</Text>
        </Pressable>
      ) : null}
      {item.priority ? <PriorityBadge priority={item.priority} /> : null}
      <SyncBadge state={item.syncState} />

      <Card style={styles.card}>
        <DetailRow label="Matter type" value={item.matterType} />
        <DetailRow label="Court" value={item.courtLabel} />
        <DetailRow label="Instructed" value={item.openedDate} />
        <DetailRow label="Next deadline" value={item.nextDueAt?.slice(0, 10)} />
        <DetailRow label="Prescribes" value={item.prescribesAt?.slice(0, 10)} />
        <DetailRow label="Closed" value={item.closedDate} />
        <DetailRow label="My role" value={item.myCapacities?.length ? item.myCapacities.join(', ') : undefined} />
        {item.documentsCount !== undefined ? <DetailRow label="Documents" value={String(item.documentsCount)} /> : null}
      </Card>

      {parties.length ? (
        <>
          <SectionHeader title="Parties" />
          {parties.map(p => (
            <Card key={p.id}>
              <Text style={styles.diaryTitle}>{p.name}</Text>
              <Text style={styles.diaryDate}>{p.roleLabel}{p.representative ? ` · ${p.representative}` : ''}</Text>
            </Card>
          ))}
        </>
      ) : null}

      <SectionHeader title="Team" />
      {(item.assignments ?? []).length === 0 ? <Text style={styles.note}>No assignments loaded.</Text> : null}
      {(item.assignments ?? []).map(a => (
        <Card key={a.id}>
          <Text style={styles.diaryTitle}>{a.userName ?? nameOf(a.userId)}</Text>
          <Text style={styles.diaryDate}>{a.capacity}</Text>
          {canAssign ? <Text style={styles.removeLink} onPress={() => unassign(a.id, a.userName ?? nameOf(a.userId))}>Remove</Text> : null}
        </Card>
      ))}
      {canAssign && capacities.length ? (
        <Card style={styles.card}>
          <ChipSelect
            label="Assign someone"
            options={users.map(u => ({ value: u.id, label: u.fullName }))}
            selected={pickUser}
            onChange={ids => setPickUser(ids.slice(-1))}
          />
          <ChipSelect
            label="Capacity"
            options={capacities.map(c => ({ value: c.id, label: c.name }))}
            selected={pickCapacity}
            onChange={ids => ids.length && setPickCapacity(ids.slice(-1))}
          />
          <Button label="Assign" variant="secondary" onPress={assign} loading={busy} />
        </Card>
      ) : null}

      <SectionHeader title="Diary" />
      {diary.length === 0 ? <Text style={styles.note}>Nothing in the diary for this matter.</Text> : null}
      {diary.map(d => (
        <Pressable key={d.id} onPress={() => router.push({ pathname: '/diary/[darzationId]', params: { darzationId: d.id } })}>
          <Card style={d.overdue ? styles.overdue : undefined}>
            <Text style={styles.diaryTitle}>{d.body || d.title}</Text>
            <Text style={styles.diaryDate}>
              {d.overdue ? 'Overdue · ' : ''}Due {formatDue(d.dueAt) || d.dueDate}
              {d.assigneeName ? ` · ${d.assigneeName}` : ''}
            </Text>
          </Card>
        </Pressable>
      ))}
      {canWorkOn(item) ? (
        <Button
          label="+ Add diary entry"
          variant="secondary"
          onPress={() => router.push({ pathname: '/diary/new', params: { caseId } })}
          style={styles.action}
        />
      ) : null}
      <Button
        label="Activity log (narrations)"
        onPress={() => router.push({ pathname: '/cases/[caseId]/activity', params: { caseId } })}
        style={styles.action}
      />
      {hasFeature('documents') ? (
        <Button
          label="Documents"
          variant="secondary"
          onPress={() => router.push({ pathname: '/cases/[caseId]/documents', params: { caseId } })}
          style={styles.action}
        />
      ) : null}
      {item.can?.view_audit ? (
        <Button
          label="Audit trail"
          variant="secondary"
          onPress={() => router.push({ pathname: '/cases/[caseId]/audit', params: { caseId } })}
          style={styles.action}
        />
      ) : null}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.background },
  content: { padding: spacing.md, paddingBottom: spacing.xl * 2 },
  headerRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  fileRef: { ...typography.caption, color: colors.textSecondary, fontFamily: fontFamily.sansSemibold },
  client: { ...typography.h1, color: colors.textPrimary, marginTop: spacing.xs, marginBottom: spacing.xs },
  card: { gap: spacing.xs, marginTop: spacing.md },
  cardTitle: { ...typography.h3, color: colors.navy },
  body: { ...typography.body, color: colors.textPrimary },
  note: { ...typography.body, color: colors.textSecondary, padding: spacing.md },
  overdue: { borderColor: colors.red },
  diaryTitle: { ...typography.body, fontFamily: fontFamily.sansSemibold, color: colors.textPrimary },
  diaryDate: { ...typography.caption, color: colors.textSecondary, marginTop: spacing.xs },
  action: { marginTop: spacing.sm },
  removeLink: { ...typography.caption, color: colors.danger, fontFamily: fontFamily.sansSemibold, marginTop: spacing.xs },
  link: { ...typography.body, color: colors.navy, fontFamily: fontFamily.sansSemibold, marginBottom: spacing.xs },
});
