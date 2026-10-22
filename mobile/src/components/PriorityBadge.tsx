import React from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { colors, radius, spacing, typography } from '@/theme/index';

export type Priority = 'Low' | 'Medium' | 'High' | 'Urgent';

const PRIORITY_COLORS: Record<Priority, { fg: string; bg: string }> = {
    Low: { fg: colors.green, bg: colors.greenTint },
    Medium: { fg: colors.navy, bg: colors.navyTint },
    High: { fg: colors.gold, bg: colors.goldTint },
    Urgent: { fg: colors.red, bg: colors.redTint },
};

export default function PriorityBadge({ priority }: { priority: Priority }) {
    const { fg, bg } = PRIORITY_COLORS[priority] ?? PRIORITY_COLORS.Medium;
    return (
        <View style={[styles.badge, { backgroundColor: bg }]}>
            <Text style={[styles.text, { color: fg }]}>{priority}</Text>
        </View>
    );
}

const styles = StyleSheet.create({
    badge: { paddingHorizontal: spacing.sm, paddingVertical: 2, borderRadius: radius.sm, alignSelf: 'flex-start' },
    text: { ...typography.caption, fontWeight: '700' },
});
