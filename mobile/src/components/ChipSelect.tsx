import React from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { colors, radius, spacing, typography } from '@/theme/index';

interface ChipSelectProps<T extends string> {
    label: string;
    options: { value: T; label: string }[];
    selected: T[];
    onChange: (next: T[]) => void;
    multiple?: boolean;
}

/** Tap-to-toggle chips: single-select by default, multi-select with `multiple`. */
export default function ChipSelect<T extends string>({
    label,
    options,
    selected,
    onChange,
    multiple = false,
}: Readonly<ChipSelectProps<T>>) {
    const toggleSingle = (value: T) => {
        onChange([value]);
    }

    const toggleMultiple = (value: T) =>{
        const next = selected.includes(value)?selected.filter((v) => v !== value) : [...selected, value];
        onChange(next);
    }
    const toggle = multiple ? toggleMultiple : toggleSingle;

    return (
        <View style={styles.container}>
            <Text style={styles.label}>{label}</Text>
            <View style={styles.row}>
                {options.map(option => {
                    const active = selected.includes(option.value);
                    return (
                        <Pressable
                            key={option.value}
                            accessibilityRole="button"
                            accessibilityState={{ selected: active }}
                            onPress={() => toggle(option.value)}
                            style={[styles.chip, active && styles.chipActive]}
                        >
                            <Text style={[styles.chipText, active && styles.chipTextActive]}>{option.label}</Text>
                        </Pressable>
                    );
                })}
            </View>
        </View>
    );
}

const styles = StyleSheet.create({
    container: { marginBottom: spacing.md },
    label: { ...typography.caption, color: colors.textSecondary, marginBottom: spacing.xs },
    row: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.xs },
    chip: {
        paddingHorizontal: spacing.md,
        paddingVertical: spacing.xs + 2,
        borderRadius: radius.md,
        borderWidth: 1,
        borderColor: colors.border,
        backgroundColor: colors.surface,
    },
    chipActive: { backgroundColor: colors.navy, borderColor: colors.navy },
    chipText: { ...typography.caption, color: colors.textPrimary },
    chipTextActive: { color: colors.white, fontWeight: '600' },
});
