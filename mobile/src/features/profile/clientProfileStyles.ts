import { StyleSheet } from 'react-native';
import { colors, radius, spacing, typography } from '@/theme/index';

export const styles = StyleSheet.create({
    container: { flex: 1, backgroundColor: colors.background },
    content: { padding: spacing.md, paddingBottom: spacing.xl * 2 },
    title: { ...typography.h1, color: colors.navy, marginBottom: spacing.sm },
    note: { ...typography.caption, color: colors.textSecondary, marginBottom: spacing.sm },
    muted: { ...typography.caption, color: colors.textSecondary },
    body: { ...typography.body, color: colors.textPrimary },
    card: { marginTop: spacing.sm },
    cardTitle: { ...typography.body, fontWeight: '700', color: colors.textPrimary },
    actions: { flexDirection: 'row', gap: spacing.sm, marginVertical: spacing.sm },
    action: {
        flex: 1,
        alignItems: 'center',
        paddingVertical: spacing.sm + 2,
        borderRadius: radius.md,
        borderWidth: 1,
        borderColor: colors.navy,
        backgroundColor: colors.surface,
    },
    actionDisabled: { borderColor: colors.border, opacity: 0.5 },
    actionText: { ...typography.caption, fontWeight: '700', color: colors.navy },
    actionTextDisabled: { color: colors.textSecondary },
    row: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: spacing.md, marginBottom: spacing.md },
    flex: { flex: 1 },
    consentRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: spacing.md, paddingVertical: spacing.xs },
    consentLabel: { ...typography.body, fontWeight: '600', color: colors.textPrimary },
    multiline: { minHeight: 70, textAlignVertical: 'top' },
    gap: { marginTop: spacing.sm },
});