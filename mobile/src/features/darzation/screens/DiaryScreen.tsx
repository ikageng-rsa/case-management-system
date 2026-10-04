import React, { useCallback, useEffect, useState } from 'react';
import { FlatList, StyleSheet, Text, View } from 'react-native';
import { useRouter } from 'expo-router';
import { fetchDiary } from '@/api/endpoint/darzations.api';
import Card from '@/components/Card';
import EmptyState from '@/components/EmptyState';
import LoadingSpinner from '@/components/LoadingSpinner';
import { colors, spacing, typography } from '@/theme/index';
import { Darzation } from '@/models/index';

export default function DiaryScreen() {
  const router = useRouter();
  const [items, setItems] = useState<Darzation[]>([]);
  const [loading, setLoading] = useState(true);

  const load = useCallback(() => {
    fetchDiary()
      .then(setItems)
      .finally(() => setLoading(false));
  }, []);

  const refresh = useCallback(() => {
    setLoading(true);
    load();
  }, [load]);

  useEffect(() => {
    load();
  }, [load]);

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
      <FlatList
        data={items}
        keyExtractor={item => item.id}
        onRefresh={refresh}
        refreshing={loading}
        contentContainerStyle={styles.list}
        renderItem={({ item }) => (
          <Card style={item.overdue ? styles.overdueCard : undefined}>
            <Text style={styles.nextAction}>{item.nextAction}</Text>
            <Text style={styles.date}>{item.nextActionDate}</Text>
          </Card>
        )}
        ListEmptyComponent={<EmptyState title="Diary is clear" message="No upcoming actions." />}
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
  title: { ...typography.h1, color: colors.navy },
  overdueLink: { ...typography.caption, color: colors.red, fontWeight: '600' },
  list: { padding: spacing.md },
  overdueCard: { borderColor: colors.red },
  nextAction: { ...typography.body, color: colors.textPrimary, fontWeight: '600' },
  date: { ...typography.caption, color: colors.textSecondary, marginTop: spacing.xs },
});
