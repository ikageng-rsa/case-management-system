import React, { useEffect, useState } from 'react';
import { ScrollView, StyleSheet, Text, View } from 'react-native';
import { useLocalSearchParams } from 'expo-router';
import { fetchCaseById } from '@/api/endpoint/cases.api';
import Card from '@/components/Card';
import LoadingSpinner from '@/components/LoadingSpinner';
import CaseStatusBadge from '@/features/cases/components/CaseStatusBadge';
import { colors, spacing, typography } from '@/theme/index';
import { Case } from '@/models/index';

export default function CaseDetailScreen() {
  const { caseId } = useLocalSearchParams<{ caseId: string }>();
  const [item, setItem] = useState<Case | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchCaseById(caseId)
      .then(setItem)
      .catch(error => console.error('Error found in case: ',error))
      .finally(() => setLoading(false));
  }, [caseId]);

  if (loading) {
    return <LoadingSpinner />;
  }
  if (!item) {
    return null;
  }

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content}>
      <View style={styles.headerRow}>
        <Text style={styles.fileRef}>{item.fileReference}</Text>
        <CaseStatusBadge status={item.status} />
      </View>
      <Text style={styles.client}>{item.clientName}</Text>

      <Card style={styles.card}>
        <Row label="Matter type" value={item.matterType} />
        <Row label="Opened" value={item.openedDate} />
        {item.opposingParty ? <Row label="Opposing party" value={item.opposingParty} /> : null}
      </Card>
    </ScrollView>
  );
}

function Row({ label, value }: { label: string; value: string }) {
  return (
    <View style={styles.row}>
      <Text style={styles.rowLabel}>{label}</Text>
      <Text style={styles.rowValue}>{value}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.background,
  },
  content: {
    padding: spacing.md,
  },
  headerRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  fileRef: {
    ...typography.caption,
    color: colors.textSecondary,
    fontWeight: '600',
  },
  client: {
    ...typography.h1,
    color: colors.textPrimary,
    marginTop: spacing.xs,
    marginBottom: spacing.md,
  },
  card: {
    gap: spacing.sm,
  },
  row: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    paddingVertical: spacing.xs,
  },
  rowLabel: {
    ...typography.body,
    color: colors.textSecondary,
  },
  rowValue: {
    ...typography.body,
    color: colors.textPrimary,
    fontWeight: '600',
  },
});
