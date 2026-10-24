import React, { useCallback, useEffect, useState } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { fetchCaseById } from '@/api/endpoint/cases.api';
import { fetchDiary } from '@/api/endpoint/darzations.api';
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
import { canEditRecords } from '@/constants/roles';
import { canSeeCase } from '@/utils/caseAccess';
import { formatCurrency } from '@/utils/formatCurrency';
import { colors, spacing, typography } from '@/theme/index';
import { Case, Darzation } from '@/models/index';

export default function CaseDetailScreen() {
  const router = useRouter();
  const { caseId } = useLocalSearchParams<{ caseId: string }>();
  const me = useAppSelector(state => state.auth.user);
  const users = useAssignableUsers();
  const [item, setItem] = useState<Case | null>(null);
  const [diary, setDiary] = useState<Darzation[]>([]);
  const [loading, setLoading] = useState(true);

  const load = useCallback(() => {
    Promise.all([fetchCaseById(caseId), fetchDiary().catch(() => [] as Darzation[])])
      .then(([c, d]) => {
        setItem(c);
        setDiary(d.filter(x => x.caseId === caseId));
      })
      .catch(() => setItem(null))
      .finally(() => setLoading(false));
  }, [caseId]);

  useEffect(() => {
    load();
  }, [load]);
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

  const nameOf = (id: string) => users.find(u => u.id === id)?.fullName ?? id;
  const canEdit = me ? canEditRecords(me.role) : false;

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content}>
      <View style={styles.headerRow}>
        <Text style={styles.fileRef}>{item.fileReference}</Text>
        <CaseStatusBadge status={item.status} />
      </View>
      <Text style={styles.client}>{item.clientName}</Text>
      {item.priority ? <PriorityBadge priority={item.priority} /> : null}
      <SyncBadge state={item.syncState} />

      {item.issueDescription ? (
        <Card style={styles.card}>
          <Text style={styles.cardTitle}>Issue</Text>
          <Text style={styles.body}>{item.issueDescription}</Text>
        </Card>
      ) : null}

      <Card style={styles.card}>
        <DetailRow label="Matter type" value={item.matterType} />
        <DetailRow label="Practice area" value={item.practiceArea} />
        <DetailRow label="Court case no." value={item.courtCaseNumber} />
        <DetailRow label="Jurisdiction" value={item.jurisdiction} />
        <DetailRow label="Opened" value={item.openedDate} />
        <DetailRow label="Next court date" value={item.nextCourtDate} />
        <DetailRow label="Closed" value={item.closedDate} />
        <DetailRow label="Estimated value" value={item.estimatedValue !== undefined ? formatCurrency(item.estimatedValue) : undefined} />
        <DetailRow label="Opposing party" value={item.opposingParty} />
        <DetailRow label="Opposing attorney" value={item.opposingAttorney} />
        <DetailRow label="Lead attorney" value={item.leadAttorneyId ? nameOf(item.leadAttorneyId) : undefined} />
        <DetailRow label="Assigned to" value={item.assignedUserIds?.map(nameOf).join(', ')} />
      </Card>

      {item.customFields && Object.keys(item.customFields).length ? (
        <Card style={styles.card}>
          <Text style={styles.cardTitle}>Additional details</Text>
          {Object.entries(item.customFields).map(([k, v]) => (
            <DetailRow key={k} label={k} value={v} />
          ))}
        </Card>
      ) : null}

      {item.notes ? (
        <Card style={styles.card}>
          <Text style={styles.cardTitle}>Notes</Text>
          <Text style={styles.body}>{item.notes}</Text>
        </Card>
      ) : null}

      <SectionHeader title="Diary" />
      {diary.length === 0 ? <Text style={styles.note}>No diary items for this case.</Text> : null}
      {diary.map(d => (
        <Pressable key={d.id} onPress={() => router.push({ pathname: '/diary/[darzationId]', params: { darzationId: d.id } })}>
          <Card style={d.overdue ? styles.overdue : undefined}>
            <Text style={styles.diaryTitle}>{d.title || d.nextAction}</Text>
            <Text style={styles.diaryDate}>Due {d.dueDate || d.nextActionDate}</Text>
          </Card>
        </Pressable>
      ))}
      {canEdit ? (
        <Button
          label="+ New diary item"
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
      <Button
        label="Documents"
        variant="secondary"
        onPress={() => router.push({ pathname: '/cases/[caseId]/documents', params: { caseId } })}
        style={styles.action}
      />
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.background },
  content: { padding: spacing.md, paddingBottom: spacing.xl * 2 },
  headerRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  fileRef: { ...typography.caption, color: colors.textSecondary, fontWeight: '600' },
  client: { ...typography.h1, color: colors.textPrimary, marginTop: spacing.xs, marginBottom: spacing.xs },
  card: { gap: spacing.xs, marginTop: spacing.md },
  cardTitle: { ...typography.h3, color: colors.navy },
  body: { ...typography.body, color: colors.textPrimary },
  note: { ...typography.body, color: colors.textSecondary, padding: spacing.md },
  overdue: { borderColor: colors.red },
  diaryTitle: { ...typography.body, fontWeight: '600', color: colors.textPrimary },
  diaryDate: { ...typography.caption, color: colors.textSecondary, marginTop: spacing.xs },
  action: { marginTop: spacing.sm },
});
