import React, { useEffect, useState } from 'react';
import { FlatList, StyleSheet, Text, View } from 'react-native';
import { fetchOverdueDarzations } from '@/api/endpoint/darzations.api';
import Card from '@/components/Card';
import EmptyState from '@/components/EmptyState';
import LoadingSpinner from '@/components/LoadingSpinner';
import { colors, spacing, typography } from '@/theme/index';
import { Darzation } from '@/models/index';

export default function OverdueAlertsScreen() {
  const [items, setItems] = useState<Darzation[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchOverdueDarzations()
      .then(setItems)
      .catch(error => console.error('Error found in darzation overdue: ',error))
      .finally(() => setLoading(false));
  }, []);

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
          <Card style={styles.card}>
            <Text style={styles.nextAction}>{item.nextAction}</Text>
            <Text style={styles.date}>Was due {item.nextActionDate}</Text>
          </Card>
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
