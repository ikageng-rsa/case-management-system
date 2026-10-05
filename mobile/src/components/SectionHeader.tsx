import React from 'react';
import { StyleSheet, Text, View, ViewStyle } from 'react-native';
import { colors, spacing, typography } from '@/theme/index';

interface SectionHeaderProps {
  readonly title: string;
  readonly eyebrow?: string; // small tracked-out label above the title, e.g. "CASE FILE"
  readonly trailing?: React.ReactNode; // optional right-aligned action, e.g. a "See all" link
  readonly style?: ViewStyle;
}

export default function SectionHeader({ title, eyebrow, trailing, style }: SectionHeaderProps) {
  return (
    <View style={[styles.container, style]}>
      <View style={styles.textBlock}>
        {eyebrow ? <Text style={styles.eyebrow}>{eyebrow}</Text> : null}
        <View style={styles.titleRow}>
          <Text style={styles.title}>{title}</Text>
          {trailing ? <View>{trailing}</View> : null}
        </View>
        <View style={styles.rule} />
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    marginBottom: spacing.md,
  },
  textBlock: {
    width: '100%',
  },
  eyebrow: {
    ...typography.overline,
    color: colors.gold,
    marginBottom: 2,
  },
  titleRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-end',
  },
  title: {
    ...typography.h1,
    color: colors.navy,
  },
  rule: {
    height: 2,
    width: 40,
    backgroundColor: colors.gold,
    marginTop: spacing.xs,
    borderRadius: 1,
  },
});
