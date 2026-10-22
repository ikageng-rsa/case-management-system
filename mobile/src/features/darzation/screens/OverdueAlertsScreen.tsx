import React, { useEffect, useState, useCallback } from 'react';
import { FlatList, Pressable, StyleSheet, Text, View } from 'react-native';
import { useRouter } from 'expo-router';
import { fetchOverdueDarzations } from '@/api/endpoint/darzations.api';
import { fetchCases } from '@/api/endpoint/cases.api';
import Card from '@/components/Card';
import EmptyState from '@/components/EmptyState';
import LoadingSpinner from '@/components/LoadingSpinner';
import { useAppSelector } from '@/hooks/useAppDispatch';
import { visibleDarzationsFor } from '@/utils/caseAccess';
import { colors, spacing, typography } from '@/theme/index';
import { useOnSynced } from '@/hooks/useSyncStatus';
import { Darzation } from '@/models/index';

export default function OverdueAlertsScreen() {
  const router = useRouter();
  const me = useAppSelector(state => state.auth.user);
  const [items, setItems] = useState<Darzation[]>([]);
  const [loading, setLoading] = useState(true);

  const load = useCallback(() => {
    Promise.all([fetchOverdueDarzations(), fetchCases().catch(() => [])])
      .then(([overdue, cases]) => setItems(visibleDarzationsFor(overdue, cases, me)))
      .finally(() => setLoading(false));
  }, [me]);

  useEffect(() => {
    load();
  }, [load]);
  useOnSynced(load);

  if (loading) {
    return <LoadingSpinner />;
  }

  return (
    <View style={styles.container}>
      <FlatList
        data={items}
        keyExtractor={item => item.id}
        contentContainerStyle={styles.list}
        renderItem={({ item }) => (
          <Pressable onPress={() => router.push({ pathname: './diary/[darzationId]', params: { darzationId: item.id } })}>
            <Card style={styles.card}>
              <Text style={styles.nextAction}>{item.title || item.nextAction}</Text>
              <Text style={styles.date}>Was due {item.dueDate || item.nextActionDate}</Text>
            </Card>
          </Pressable>
        )}
        ListEmptyComponent={<EmptyState title="Nothing overdue" message="Every diary item is up to date." />}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.background },
  list: { padding: spacing.md },
  card: { borderColor: colors.red },
  nextAction: { ...typography.body, color: colors.textPrimary, fontWeight: '600' },
  date: { ...typography.caption, color: colors.red, marginTop: spacing.xs },
});
