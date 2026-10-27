import { StyleSheet } from 'react-native';
import { colors, spacing, typography } from '@/theme/index';

export  const styles = StyleSheet.create({
    container: { flex: 1, backgroundColor: colors.background },
    content: { padding: spacing.lg, paddingBottom: spacing.xl * 2 },
    title: { ...typography.h1, color: colors.navy },
    scope: { ...typography.caption, color: colors.textSecondary, marginBottom: spacing.md },
    error: { ...typography.body, color: colors.danger, marginBottom: spacing.md },
    grid: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.md, marginBottom: spacing.lg },
    tileWrap: { width: '47%' },
    tile: { marginBottom: 0 },
    tileValue: { ...typography.h1, fontSize: 28 },
    tileLabel: { ...typography.body, color: colors.textSecondary, marginTop: spacing.xs },
    overdueCard: { borderColor: colors.red },
    overdueText: { ...typography.caption, color: colors.red, marginTop: spacing.xs },
    itemTitle: { ...typography.body, fontWeight: '600', color: colors.textPrimary },
    itemMeta: { ...typography.caption, color: colors.textSecondary, marginVertical: 2 },
});

