import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { FlatList, Pressable, StyleSheet, Text, View } from 'react-native';
import { useRouter } from 'expo-router';
import { fetchDiary } from '@/api/endpoint/darzations.api';
import { fetchCases } from '@/api/endpoint/cases.api';
import Card from '@/components/Card';
import Button from '@/components/Button';
import ChipSelect from '@/components/ChipSelect';
import EmptyState from '@/components/EmptyState';
import LoadingSpinner from '@/components/LoadingSpinner';
import PriorityBadge from '@/components/PriorityBadge';
import SyncBadge from '@/components/SyncBadge';
import { useOnSynced } from '@/hooks/useSyncStatus';
import { useAppSelector } from '@/hooks/useAppDispatch';
import { canEditRecords } from '@/constants/roles';
import { visibleDarzationsFor } from '@/utils/caseAccess';
import { colors, spacing, typography } from '@/theme/index';
import { Darzation, DarzationStatus } from '@/models/index';

type Filter = 'All' | DarzationStatus | 'Overdue';
const FILTERS: Filter[] = ['All', 'Overdue', 'Pending', 'In Progress', 'Done'];

export default function DiaryScreen() {
  const router = useRouter();
  const me = useAppSelector(state => state.auth.user);
  const [items, setItems] = useState<Darzation[]>([]);
  const [loading, setLoading] = useState(true);
  const [filter, setFilter] = useState<Filter[]>(['All']);

  const load = useCallback(() => {
    Promise.all([fetchDiary(), fetchCases().catch(() => [])])
      .then(([diary, cases]) => setItems(visibleDarzationsFor(diary, cases, me)))
      .catch((error) => {console.error('Failed to load diary items:',error)})
      .finally(() => setLoading(false));
  }, [me]);

  const refresh = useCallback(() => {
    setLoading(true);
    load();
  }, [load]);

  useEffect(() => {
    load();
  }, [load]);
  useOnSynced(load);

  const shown = useMemo(() => {
    const f = filter[0];
    const sorted = [...items].sort((a, b) => (a.dueDate || a.nextActionDate).localeCompare(b.dueDate || b.nextActionDate));
    if (f === 'All') return sorted;
    if (f === 'Overdue') return sorted.filter(i => i.overdue);
    return sorted.filter(i => i.status === f);
  }, [items, filter]);

  if (loading && items.length === 0) {
    return <LoadingSpinner />;
  }

  return (
    <View style={styles.container}>
      <View style={styles.headerRow}>
        <Text style={styles.title}>Diary</Text>
        <Text style={styles.overdueLink} onPress={() => router.push('/diary/overdue')}>
          View overdue →
        </Text>
      </View>
      <View style={styles.filters}>
        <ChipSelect label="Show" options={FILTERS.map(f => ({ value: f, label: f }))} selected={filter} onChange={setFilter} />
        {me && canEditRecords(me.role) ? <Button label="+ New diary item" onPress={() => router.push('/diary/new')} /> : null}
      </View>
      <FlatList
        data={shown}
        keyExtractor={item => item.id}
        onRefresh={refresh}
        refreshing={loading}
        contentContainerStyle={styles.list}
        renderItem={({ item }) => (
          <Pressable onPress={() => router.push({ pathname: '/diary/[darzationId]', params: { darzationId: item.id } })}>
            <Card style={item.overdue ? styles.overdueCard : undefined}>
              <Text style={styles.nextAction}>{item.title || item.nextAction}</Text>
              <Text style={styles.date}>
                Due {item.dueDate || item.nextActionDate} · {item.status ?? 'Pending'}
                {item.steps?.length ? ` · ${item.steps.filter(s => s.done).length}/${item.steps.length} steps` : ''}
              </Text>
              {item.priority ? <PriorityBadge priority={item.priority} /> : null}
              <SyncBadge state={item.syncState} />
            </Card>
          </Pressable>
        )}
        ListEmptyComponent={<EmptyState title="Diary is clear" message="No matching actions." />}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.background },
  headerRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingHorizontal: spacing.md,
    paddingTop: spacing.md,
  },
  filters: { paddingHorizontal: spacing.md, paddingTop: spacing.md },
  title: { ...typography.h1, color: colors.navy },
  overdueLink: { ...typography.caption, color: colors.red, fontWeight: '600' },
  list: { padding: spacing.md },
  overdueCard: { borderColor: colors.red },
  nextAction: { ...typography.body, color: colors.textPrimary, fontWeight: '600' },
  date: { ...typography.caption, color: colors.textSecondary, marginVertical: spacing.xs },
});
