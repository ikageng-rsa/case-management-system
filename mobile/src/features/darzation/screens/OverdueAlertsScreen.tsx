import React, { useCallback, useEffect, useState } from 'react';
import { FlatList, Pressable, StyleSheet, Text, View } from 'react-native';
import { useRouter } from 'expo-router';
import { fetchOverdueDarzations } from '@/api/endpoint/darzations.api';
import Card from '@/components/Card';
import EmptyState from '@/components/EmptyState';
import LoadingSpinner from '@/components/LoadingSpinner';
import { useOnSynced } from '@/hooks/useSyncStatus';
import { formatDue } from '@/utils/dueAt';
import { colors, fontFamily, spacing, typography } from '@/theme/index';
import { Darzation } from '@/models/index';

export default function OverdueAlertsScreen() {
  const router = useRouter();
  const [items, setItems] = useState<Darzation[]>([]);
  const [loading, setLoading] = useState(true);

  const load = useCallback(() => {
    fetchOverdueDarzations()
      .then(setItems)
      .catch(() => setItems([]))
      .finally(() => setLoading(false));
  }, []);

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
          <Pressable onPress={() => router.push({ pathname: '/diary/[darzationId]', params: { darzationId: item.id } })}>
            <Card style={styles.card}>
              <Text style={styles.body}>{item.body || item.title}</Text>
              <Text style={styles.date}>Was due {formatDue(item.dueAt) || item.dueDate}</Text>
            </Card>
          </Pressable>
        )}
        ListEmptyComponent={<EmptyState title="Nothing overdue" message="Every diary entry is up to date." />}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.background },
  list: { padding: spacing.md },
  card: { borderColor: colors.red },
  body: { ...typography.body, color: colors.textPrimary, fontFamily: fontFamily.sansSemibold },
  date: { ...typography.caption, color: colors.red, marginTop: spacing.xs },
});
