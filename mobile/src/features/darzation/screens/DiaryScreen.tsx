import React, { useCallback, useMemo, useState } from 'react';
import { FlatList, Pressable, StyleSheet, Text, View } from 'react-native';
import { useFocusEffect, useRouter } from 'expo-router';
import { fetchCompletedDiary, fetchDiary } from '@/api/endpoint/darzations.api';
import { getApiErrorMessage } from '@/api/errors';
import Button from '@/components/Button';
import Card from '@/components/Card';
import ChipSelect from '@/components/ChipSelect';
import EmptyState from '@/components/EmptyState';
import LoadingSpinner from '@/components/LoadingSpinner';
import SyncBadge from '@/components/SyncBadge';
import { StageBadge } from '@/ui';
import { canEditRecords } from '@/constants/roles';
import { useOnSynced } from '@/hooks/useSyncStatus';
import { useAppSelector } from '@/hooks/useAppDispatch';
import { formatDue } from '@/utils/dueAt';
import { colors, fontFamily, spacing, typography } from '@/theme/index';
import { Darzation } from '@/models/index';

type Filter = 'Pending' | 'Overdue' | 'Completed';
const FILTERS: Filter[] = ['Pending', 'Overdue', 'Completed'];

export default function DiaryScreen() {
  const router = useRouter();
  const me = useAppSelector(state => state.auth.user);
  const [open, setOpen] = useState<Darzation[]>([]);
  const [completed, setCompleted] = useState<Darzation[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [filter, setFilter] = useState<Filter[]>(['Pending']);
  const active = filter[0] ?? 'Pending';

  const load = useCallback(() => {
    Promise.all([fetchDiary(), fetchCompletedDiary().catch(() => [] as Darzation[])])
      .then(([openItems, doneItems]) => {
        setOpen(openItems);
        setCompleted(doneItems);
        setError(null);
      })
      .catch(e => setError(getApiErrorMessage(e, 'The diary could not be loaded.')))
      .finally(() => setLoading(false));
  }, []);

  const refresh = useCallback(() => {
    setLoading(true);
    load();
  }, [load]);

  // Reload every time the tab/screen is shown, so an entry just added, edited or completed is up to date.
  useFocusEffect(
    useCallback(() => {
      load();
    }, [load]),
  );
  useOnSynced(load);

  const shown = useMemo(() => {
    if (active === 'Completed') return completed;
    if (active === 'Overdue') return open.filter(i => i.overdue);
    return open.filter(i => !i.overdue);
  }, [open, completed, active]);

  const overdueCount = open.filter(i => i.overdue).length;

  if (loading && open.length === 0 && completed.length === 0) {
    return <LoadingSpinner />;
  }

  return (
    <View style={styles.container}>
      <View style={styles.headerRow}>
        <Text style={styles.title}>Diary</Text>
        {me && canEditRecords(me.role) ? <Button label="+ New entry" onPress={() => router.push('/diary/new')} /> : null}
      </View>
      <View style={styles.filters}>
        <ChipSelect
          label="Show"
          options={FILTERS.map(f => ({ value: f, label: f === 'Overdue' && overdueCount ? `Overdue (${overdueCount})` : f }))}
          selected={filter}
          onChange={setFilter}
        />
      </View>
      <FlatList
        data={shown}
        keyExtractor={item => item.id}
        onRefresh={refresh}
        refreshing={loading}
        contentContainerStyle={styles.list}
        renderItem={({ item }) => (
          <Pressable
            accessibilityRole="button"
            onPress={() => router.push({ pathname: '/diary/[darzationId]', params: { darzationId: item.id } })}
          >
            <Card style={item.overdue ? styles.overdueCard : undefined}>
              <Text style={styles.body} numberOfLines={3}>
                {item.body || item.title}
              </Text>
              <Text style={styles.date}>
                {item.status === 'Done' ? `Done ${formatDue(item.completedAt ?? undefined)}` : `Due ${formatDue(item.dueAt) || item.dueDate}`}
                {item.matterReference ? ` · ${item.matterReference}` : ''}
              </Text>
              <View style={styles.badges}>
                {item.overdue ? <StageBadge tone="danger">Overdue</StageBadge> : null}
                {!item.overdue && item.isDue && item.status !== 'Done' ? <StageBadge tone="accent">Due now</StageBadge> : null}
                {item.status === 'Done' ? <StageBadge tone="success">Done</StageBadge> : null}
                {item.reminderLabel && item.status !== 'Done' ? <StageBadge tone="neutral">{`Reminder: ${item.reminderLabel}`}</StageBadge> : null}
                <SyncBadge state={item.syncState} />
              </View>
            </Card>
          </Pressable>
        )}
        ListEmptyComponent={
          <EmptyState
            title={error ? 'Could not load the diary' : 'Diary is clear'}
            message={error ?? (active === 'Completed' ? 'Nothing has been marked done yet.' : 'No matching entries.')}
          />
        }
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
  list: { padding: spacing.md },
  overdueCard: { borderColor: colors.red },
  body: { ...typography.body, color: colors.textPrimary, fontFamily: fontFamily.sansSemibold },
  date: { ...typography.caption, color: colors.textSecondary, marginVertical: spacing.xs },
  badges: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.xs, alignItems: 'center' },
});
