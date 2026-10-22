import React from 'react';
import { StyleSheet, Text,View } from 'react-native';
import { colors,spacing,typography } from '@/theme/index';

//Label/value row; renders nothing when the value is empty so optional fields don't leave blanks.
export default function DetailRow({label,value}: {label: string, value?:string | number | null}){
    if(value === undefined || value === null || value === ''){
        return null;
    }

    return (
        <View style={styles.row}>
            <Text style={styles.label}>{label}</Text>
            <Text style={styles.value}>{String(value)}</Text>
        </View>
    );
}

const styles = StyleSheet.create({
    row: {flexDirection: 'row', justifyContent: 'space-between', paddingVertical: spacing.xs, gap: spacing.md},
    label: { ...typography.body, color: colors.textSecondary},
    value: { ...typography.body, color: colors.textPrimary, fontWeight: '600', flexShrink: 1, textAlign: 'right'}
})