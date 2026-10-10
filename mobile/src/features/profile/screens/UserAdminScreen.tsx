import React from 'react';
import { StyleSheet, Text, View } from 'react-native';
import EmptyState from '@/components/EmptyState';
import { colors, spacing, typography } from '@/theme/index';

// Admin-only screen — gate access to this at the navigator level using
// TAB_ACCESS / canAccessTab from @constants/roles before shipping.
export default function UserAdminScreen() {
  return (
    <View style={styles.container}>
      <Text style={styles.title}>User & Role Administration</Text>
      <EmptyState
        title="No pending user requests"
        message="New staff accounts and role changes will appear here."
      />
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.background, padding: spacing.lg },
  title: { ...typography.h1, color: colors.navy, marginBottom: spacing.md },
});
