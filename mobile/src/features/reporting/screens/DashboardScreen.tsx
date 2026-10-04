import React, { useEffect, useState } from 'react';
import { ScrollView, StyleSheet, Text, View } from 'react-native';
import { useRouter } from 'expo-router';
import Card from '@/components/Card';
import LoadingSpinner from '@/components/LoadingSpinner';
import { fetchDashboardSummary, DashboardSummary } from '@/api/endpoint/reports.api';
import { colors, spacing, typography } from '@/theme/index';

export default function DashboardScreen() {
  const router = useRouter();
  const [summary, setSummary] = useState<DashboardSummary | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchDashboardSummary()
      .then(setSummary)
      .finally(() => setLoading(false));
  }, []);

  if (loading) {
    return <LoadingSpinner />;
  }
  if (!summary) {
    return null;
  }

  const tiles: { label: string; value: number; color: string; onPress?: () => void }[] = [
    {
      label: 'Open cases',
      value: summary.openCases,
      color: colors.navy,
      onPress: () => router.navigate('/cases'),
    },
    {
      label: 'Overdue diary items',
      value: summary.overdueDarzations,
      color: colors.red,
      onPress: () => router.navigate('/diary'),
    },
    {
      label: 'Unbilled entries',
      value: summary.unbilledEntries,
      color: colors.gold,
      onPress: () => router.navigate('/billing'),
    },
    { label: 'Documents this week', value: summary.documentsThisWeek, color: colors.green },
  ];

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content}>
      <Text style={styles.title}>Dashboard</Text>
      <View style={styles.grid}>
        {tiles.map(tile => (
          <Card key={tile.label} style={styles.tile}>
            <Text style={[styles.tileValue, { color: tile.color }]}>{tile.value}</Text>
            <Text style={styles.tileLabel}>{tile.label}</Text>
          </Card>
        ))}
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.background },
  content: { padding: spacing.lg },
  title: { ...typography.h1, color: colors.navy, marginBottom: spacing.md },
  grid: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.md },
  tile: { width: '47%' },
  tileValue: { ...typography.h1, fontSize: 32 },
  tileLabel: { ...typography.body, color: colors.textSecondary, marginTop: spacing.xs },
});
