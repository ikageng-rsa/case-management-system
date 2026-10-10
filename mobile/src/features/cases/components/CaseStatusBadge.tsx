import React from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { colors, radius, spacing } from '@/theme/index';
import { CaseStatus } from '@/models/index';

const STATUS_COLORS: Record<CaseStatus, string> = {
  Open: colors.green,
  'In Progress': colors.gold,
  'On Hold': colors.textSecondary,
  Closed: colors.navy,
  Archived: colors.purple,
};

export default function CaseStatusBadge({ status }: Readonly<{ status: CaseStatus }>) {
  const color = STATUS_COLORS[status];
  return (
    <View style={[styles.badge, { backgroundColor: `${color}1A`, borderColor: color }]}>
      <Text style={[styles.text, { color }]}>{status}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  badge: {
    paddingHorizontal: spacing.sm,
    paddingVertical: 2,
    borderRadius: radius.sm,
    borderWidth: 1,
    alignSelf: 'flex-start',
  },
  text: {
    fontSize: 11,
    fontWeight: '600',
  },
});
