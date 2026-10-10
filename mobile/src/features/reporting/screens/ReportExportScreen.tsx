import React from 'react';
import { StyleSheet, Text, View } from 'react-native';
import Button from '@/components/Button';
import { colors, spacing, typography } from '@/theme/index';

export default function ReportExportScreen() {
  return (
    <View style={styles.container}>
      <Text style={styles.title}>Export Reports</Text>
      <Text style={styles.note}>
        Generate a PDF/CSV export of billing, case load, or diary status. Hook this up to a backend export
        endpoint once Reporting & Analytics (Sprint 7 in the Gantt) is built.
      </Text>
      <Button label="Export billing (PDF)" onPress={() => {}} variant="secondary" style={styles.button} />
      <Button label="Export case load (CSV)" onPress={() => {}} variant="secondary" style={styles.button} />
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.background, padding: spacing.lg },
  title: { ...typography.h1, color: colors.navy, marginBottom: spacing.sm },
  note: { ...typography.body, color: colors.textSecondary, marginBottom: spacing.lg },
  button: { marginBottom: spacing.sm },
});
