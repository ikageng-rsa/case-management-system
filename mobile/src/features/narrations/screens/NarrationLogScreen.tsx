import React, { useCallback, useEffect, useState } from 'react';
import { FlatList, Pressable, StyleSheet, Text, View } from 'react-native';
import { useFocusEffect, useLocalSearchParams, useRouter } from 'expo-router';
import { fetchNarrationsForCase } from '@/api/endpoint/narrations.api';
import Card from '@/components/Card';
import SyncBadge from '@/components/SyncBadge';
import { useOnSynced } from '@/hooks/useSyncStatus';
import Button from '@/components/Button';
import EmptyState from '@/components/EmptyState';
import LoadingSpinner from '@/components/LoadingSpinner';
import { useAppSelector } from '@/hooks/useAppDispatch';
import { canEditRecords } from '@/constants/roles';
import { formatDuration } from '@/utils/formatDuration';
import { colors, spacing, typography } from '@/theme/index';
import { Narration } from '@/models/index';

export default function NarrationLogScreen() {
  const router = useRouter();
  const { caseId } = useLocalSearchParams<{ caseId: string }>();
  const me = useAppSelector(state => state.auth.user);
  const [items, setItems] = useState<Narration[]>([]);
  const [loading, setLoading] = useState(true);

  const load = useCallback(() => {
    fetchNarrationsForCase(caseId)
      .then(setItems)
      .catch(() => setItems([]))
      .finally(() => setLoading(false));
  }, [caseId]);

  useEffect(() => {
    load();
  }, [load]);
  useOnSynced(load);
  // Refresh when returning from the add screen.
  useFocusEffect(load);

  if (loading) {
    return <LoadingSpinner />;
  }

  const totalMinutes = items.reduce((sum, n) => sum + (n.timeSpentMinutes ?? 0), 0);

  return (
    <View style={styles.container}>
      <View style={styles.header}>
        {me && canEditRecords(me.role) ? (
          <Button label="+ Log activity" onPress={() => router.push({ pathname: '/cases/[caseId]/add-activity', params: { caseId } })} />
        ) : null}
        <Text style={styles.total}>Total time logged: {formatDuration(totalMinutes)}</Text>
      </View>
      <FlatList
        data={items}
        keyExtractor={item => item.id}
        contentContainerStyle={styles.list}
        renderItem={({ item }) => (
          <Pressable
            onPress={() =>
              router.push({ pathname: './cases/[caseId]/narrations/[narrationId]', params: { caseId, narrationId: item.id } })
            }
          >
            <Card>
              <View style={styles.row}>
                <Text style={styles.type}>{item.title || item.activityType}</Text>
                <Text style={styles.time}>{formatDuration(item.timeSpentMinutes ?? 0)}</Text>
              </View>
              <Text style={styles.description} numberOfLines={2}>{item.description}</Text>
              {item.billable ? <Text style={styles.billable}>Billable</Text> : null}
              <SyncBadge state={item.syncState} />
            </Card>
          </Pressable>
        )}
        ListEmptyComponent={<EmptyState title="No activity logged yet" />}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.background },
  header: { paddingHorizontal: spacing.md, paddingTop: spacing.md, gap: spacing.sm },
  total: { ...typography.caption, color: colors.textSecondary },
  list: { padding: spacing.md },
  row: { flexDirection: 'row', justifyContent: 'space-between' },
  type: { ...typography.caption, fontWeight: '700', color: colors.navy, flex: 1 },
  time: { ...typography.caption, color: colors.textSecondary },
  billable: { ...typography.caption, color: colors.green, fontWeight: '600', marginTop: spacing.xs },
  description: { ...typography.body, color: colors.textPrimary, marginTop: spacing.xs },
});
