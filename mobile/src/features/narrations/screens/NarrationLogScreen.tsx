import React, { useEffect, useState } from 'react';
import { FlatList, StyleSheet, Text, View } from 'react-native';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { fetchNarrationsForCase } from '@/api/endpoint/narrations.api';
import Card from '@/components/Card';
import Button from '@/components/Button';
import EmptyState from '@/components/EmptyState';
import LoadingSpinner from '@/components/LoadingSpinner';
import { colors, spacing, typography } from '@/theme/index';
import { Narration } from '@/models/index';

export default function NarrationLogScreen() {
  const router = useRouter();
  const { caseId } = useLocalSearchParams<{ caseId: string }>();
  const [items, setItems] = useState<Narration[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchNarrationsForCase(caseId)
      .then(setItems)
      .catch(error => console.error('Error found in narration: ',error))
      .finally(() => setLoading(false));
  }, [caseId]);

  if (loading) {
    return <LoadingSpinner />;
  }

  return (
    <View style={styles.container}>
      <View style={styles.header}>
        <Button label="+ Log activity" onPress={() => router.push({ pathname: '/cases/[caseId]/add-activity', params: { caseId } })} />
      </View>
      <FlatList
        data={items}
        keyExtractor={item => item.id}
        contentContainerStyle={styles.list}
        renderItem={({ item }) => (
          <Card>
            <View style={styles.row}>
              <Text style={styles.type}>{item.activityType}</Text>
              {item.billable ? <Text style={styles.billable}>Billable</Text> : null}
            </View>
            <Text style={styles.description}>{item.description}</Text>
          </Card>
        )}
        ListEmptyComponent={<EmptyState title="No activity logged yet" />}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.background },
  header: { paddingHorizontal: spacing.md, paddingTop: spacing.md },
  list: { padding: spacing.md },
  row: { flexDirection: 'row', justifyContent: 'space-between' },
  type: { ...typography.caption, fontWeight: '700', color: colors.navy },
  billable: { ...typography.caption, color: colors.green, fontWeight: '600' },
  description: { ...typography.body, color: colors.textPrimary, marginTop: spacing.xs },
});
