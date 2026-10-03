import React from 'react';
import { ScrollView, StyleSheet, Text, View } from 'react-native';
import Card from '@/components/Card';
import { colors, spacing, typography } from '@/theme/index';

// NOTE: wire this up to a real client record via route params once the
// Profile Management API lands (Sprint 4 in the backlog).
export default function ClientProfileScreen() {
  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content}>
      <Text style={styles.title}>Client Profile</Text>
      <Card>
        <View style={styles.row}>
          <Text style={styles.label}>FICA verified</Text>
          <Text style={styles.value}>Pending integration</Text>
        </View>
        <View style={styles.row}>
          <Text style={styles.label}>Risk rating</Text>
          <Text style={styles.value}>—</Text>
        </View>
      </Card>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.background },
  content: { padding: spacing.lg },
  title: { ...typography.h1, color: colors.navy, marginBottom: spacing.md },
  row: { flexDirection: 'row', justifyContent: 'space-between', paddingVertical: spacing.xs },
  label: { ...typography.body, color: colors.textSecondary },
  value: { ...typography.body, color: colors.textPrimary, fontWeight: '600' },
});
