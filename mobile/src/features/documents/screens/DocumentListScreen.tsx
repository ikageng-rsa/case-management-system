import React, { useEffect, useState } from 'react';
import { FlatList, Pressable, StyleSheet, Text, View } from 'react-native';
import { useNavigation, useRoute } from '@react-navigation/native';
import { fetchDocumentsForCase } from '@/api/endpoint/documents.api';
import Card from '@/components/Card';
import EmptyState from '@/components/EmptyState';
import LoadingSpinner from '@/components/LoadingSpinner';
import { colors, spacing, typography } from '@/theme/index';
import { CaseDocument } from '@/models/index';

const CONFIDENTIALITY_COLOR: Record<CaseDocument['confidentiality'], string> = {
  Standard: colors.green,
  Restricted: colors.gold,
  Privileged: colors.red,
};

export default function DocumentListScreen() {
  const route = useRoute<any>();
  const navigation = useNavigation<any>();
  const { caseId } = route.params as { caseId: string };
  const [items, setItems] = useState<CaseDocument[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchDocumentsForCase(caseId)
      .then(setItems)
      .finally(() => setLoading(false));
  }, [caseId]);

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
          <Pressable onPress={() => navigation.navigate('DocumentViewer', { documentId: item.id })}>
            <Card>
              <View style={styles.row}>
                <Text style={styles.fileName} numberOfLines={1}>
                  {item.fileName}
                </Text>
                <Text style={[styles.badge, { color: CONFIDENTIALITY_COLOR[item.confidentiality] }]}>
                  {item.confidentiality}
                </Text>
              </View>
              <Text style={styles.meta}>
                v{item.version} · {item.uploadedAt}
              </Text>
            </Card>
          </Pressable>
        )}
        ListEmptyComponent={
          <EmptyState title="No documents" message="Upload the first file for this case." />
        }
      />
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.background },
  list: { padding: spacing.md },
  row: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  fileName: {
    ...typography.body,
    color: colors.textPrimary,
    fontWeight: '600',
    flex: 1,
    marginRight: spacing.sm,
  },
  badge: { ...typography.caption, fontWeight: '700' },
  meta: { ...typography.caption, color: colors.textSecondary, marginTop: spacing.xs },
});
