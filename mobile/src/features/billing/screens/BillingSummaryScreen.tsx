import React, { useEffect, useState } from 'react';
import { FlatList, StyleSheet, Text, View } from 'react-native';
import { useLocalSearchParams } from 'expo-router';
import { fetchBillingForCase } from '@/api/endpoint/billing.api';
import Card from '@/components/Card';
import EmptyState from '@/components/EmptyState';
import LoadingSpinner from '@/components/LoadingSpinner';
import { colors, spacing, typography } from '@/theme/index';
import { BillingEntry } from '@/models/index';

export default function BillingSummaryScreen() {
  const { caseId } = useLocalSearchParams<{ caseId?: string }>();
  const [items, setItems] = useState<BillingEntry[]>([]);
  // Nothing to fetch without a caseId, so don't start in the loading state.
  const [loading, setLoading] = useState(Boolean(caseId));

  useEffect(() => {
    if (!caseId) {
      return;
    }
    fetchBillingForCase(caseId)
      .then(setItems)
      .finally(() => setLoading(false));
  }, [caseId]);

  if (loading) {
    return <LoadingSpinner />;
  }

  const total = items.reduce((sum, entry) => sum + entry.amount, 0);
  const unbilled = items.filter(entry => !entry.billed).length;

  return (
    <View style={styles.container}>
      <View style={styles.summaryRow}>
        <Text style={styles.totalLabel}>Total</Text>
        <Text style={styles.totalValue}>R {total.toFixed(2)}</Text>
        {unbilled > 0 ? <Text style={styles.unbilled}>{unbilled} unbilled</Text> : null}
      </View>
      <FlatList
        data={items}
        keyExtractor={item => item.id}
        contentContainerStyle={styles.list}
        renderItem={({ item }) => (
          <Card>
            <View style={styles.row}>
              <Text style={styles.tariff}>{item.tariffScale}</Text>
              <Text style={styles.amount}>R {item.amount.toFixed(2)}</Text>
            </View>
            <Text style={styles.status}>{item.billed ? 'Billed' : 'Unbilled'}</Text>
          </Card>
        )}
        ListEmptyComponent={<EmptyState title="No billing entries yet" />}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.background },
  summaryRow: {
    flexDirection: 'row',
    alignItems: 'baseline',
    padding: spacing.md,
    gap: spacing.sm,
  },
  totalLabel: { ...typography.body, color: colors.textSecondary },
  totalValue: { ...typography.h1, color: colors.navy },
  unbilled: { ...typography.caption, color: colors.gold, marginLeft: spacing.sm },
  list: { padding: spacing.md },
  row: { flexDirection: 'row', justifyContent: 'space-between' },
  tariff: { ...typography.body, color: colors.textPrimary, fontWeight: '600' },
  amount: { ...typography.body, color: colors.textPrimary },
  status: { ...typography.caption, color: colors.textSecondary, marginTop: spacing.xs },
});
