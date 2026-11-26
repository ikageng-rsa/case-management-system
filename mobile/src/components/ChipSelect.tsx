import React from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { Field } from '@/ui';
import { colors, fontFamily, radius, spacing, typography } from '@/theme/index';

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
}: ChipSelectProps<T>) {
    const toggle = (value: T) => {
        if (multiple) {
            onChange(selected.includes(value) ? selected.filter(v => v !== value) : [...selected, value]);
        } else {
            onChange([value]);
        }
    };

    return (
        <Field label={label}>
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
        </Field>
    );
}

const styles = StyleSheet.create({
    row: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.sm },
    chip: {
        paddingHorizontal: spacing.md - 2,
        paddingVertical: spacing.sm - 1,
        borderRadius: radius.md,
        borderWidth: 1,
        borderColor: colors.divider,
        backgroundColor: colors.surface,
    },
    chipActive: { backgroundColor: colors.navy, borderColor: colors.navy },
    chipText: { ...typography.caption, color: colors.textPrimary },
    chipTextActive: { color: colors.white, fontFamily: fontFamily.sansSemibold },
});
