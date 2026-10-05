import React from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import Card from '@/components/Card';
import CaseStatusBadge from './CaseStatusBadge';
import { colors, spacing, typography } from '@/theme/index';
import { Case } from '@/models/index';

interface CaseCardProps {
  readonly item: Case;
  readonly onPress: () => void;
}

export default function CaseCard({ item, onPress }: CaseCardProps) {
  return (
    <Pressable onPress={onPress}>
      <Card>
        <View style={styles.row}>
          <Text style={styles.fileRef}>{item.fileReference}</Text>
          <CaseStatusBadge status={item.status} />
        </View>
        <Text style={styles.client}>{item.clientName}</Text>
        <Text style={styles.matterType}>{item.matterType}</Text>
      </Card>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  row: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: spacing.xs,
  },
  fileRef: {
    ...typography.caption,
    color: colors.textSecondary,
    fontWeight: '600',
  },
  client: {
    ...typography.h2,
    color: colors.textPrimary,
    marginTop: spacing.xs,
  },
  matterType: {
    ...typography.body,
    color: colors.textSecondary,
    marginTop: 2,
  },
});
