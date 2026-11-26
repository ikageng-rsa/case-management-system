import React from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { Icon, IconName } from '@/ui';
import { colors, spacing, typography } from '@/theme/index';

interface EmptyStateProps {
    title: string;
    message?: string;
    icon?: IconName;
}

export default function EmptyState({ title, message, icon = 'inbox' }: EmptyStateProps) {
    return (
        <View style={styles.container}>
            <Icon name={icon} size="lg" color={colors.textMuted} style={styles.icon} />
            <Text style={styles.title}>{title}</Text>
            {message ? <Text style={styles.message}>{message}</Text> : null}
        </View>
    );
}

const styles = StyleSheet.create({
    container: {
        alignItems: 'center',
        justifyContent: 'center',
        paddingVertical: spacing.xl,
        paddingHorizontal: spacing.lg,
    },
    icon: {
        marginBottom: spacing.sm,
    },
    title: {
        ...typography.h2,
        color: colors.textPrimary,
        marginBottom: spacing.xs,
        textAlign: 'center',
    },
    message: {
        ...typography.body,
        color: colors.textSecondary,
        textAlign: 'center',
    },
});
