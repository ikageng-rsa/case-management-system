import React from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import Card from '@/components/Card';
import CaseStatusBadge from './CaseStatusBadge';
import PriorityBadge from '@/components/PriorityBadge';
import SyncBadge from '@/components/SyncBadge';
import { colors, fontFamily, spacing, typography } from '@/theme/index';
import { Case } from '@/models/index';

interface CaseCardProps {
  item: Case;
  onPress: () => void;
}

export default function CaseCard({ item, onPress }: CaseCardProps) {
  return (
    <Pressable onPress={onPress}>
      <Card>
        <View style={styles.row}>
          <Text style={styles.fileRef}>{item.fileReference}</Text>
          <CaseStatusBadge status={item.status} />
        </View>
        <Text style={styles.client}>{item.title || item.clientName}</Text>
        <Text style={styles.matterType}>{item.title ? `${item.clientName} · ${item.matterType}` : item.matterType}</Text>
        {item.priority ? <PriorityBadge priority={item.priority} /> : null}
        <SyncBadge state={item.syncState} />
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
    fontFamily: fontFamily.sansSemibold,
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
