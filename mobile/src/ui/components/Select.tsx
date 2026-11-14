import { useState } from 'react';
import { Modal, Pressable, ScrollView, StyleProp, StyleSheet, Text, View, ViewStyle } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { radius, space, Theme, typography, useStyles, useTheme } from '../theme';
import { Field, useFieldStyles } from './Field';
import { Icon } from './Icon';

export interface SelectOption<T> {
    label: string;
    value: T;
}

export interface SelectProps<T extends string | number> {
    options: SelectOption<T>[];
    value: T | null | undefined;
    onChange: (value: T) => void;
    label?: string;
    placeholder?: string;
    before?: string;
    hint?: string;
    error?: string;
    disabled?: boolean;
    containerStyle?: StyleProp<ViewStyle>;
}

export function Select<T extends string | number>({
    options,
    value,
    onChange,
    label,
    placeholder = 'Choose…',
    before,
    hint,
    error,
    disabled = false,
    containerStyle,
}: SelectProps<T>) {
    const theme = useTheme();
    const field = useFieldStyles();
    const styles = useStyles(makeStyles);
    const insets = useSafeAreaInsets();
    const [open, setOpen] = useState(false);
    const selected = options.find((option) => option.value === value);

    return (
        <Field label={label} before={before} hint={hint} error={error} style={containerStyle}>
            <Pressable
                accessibilityRole="button"
                accessibilityLabel={label}
                accessibilityState={{ expanded: open, disabled }}
                disabled={disabled}
                onPress={() => setOpen(true)}
                style={[field.box, styles.trigger, open && field.focused, Boolean(error) && field.invalid, disabled && styles.disabled]}
            >
                <Text style={[field.text, styles.value, !selected && styles.placeholder]} numberOfLines={1}>
                    {selected?.label ?? placeholder}
                </Text>
                <Icon name="chevron-down" size="sm" color={theme.colors.fgMuted} />
            </Pressable>

            <Modal visible={open} transparent animationType="fade" statusBarTranslucent onRequestClose={() => setOpen(false)}>
                <Pressable style={styles.backdrop} onPress={() => setOpen(false)} accessibilityLabel="Close" />

                <View style={[styles.panel, { paddingBottom: insets.bottom + space[8] }]}>
                    {label ? <Text style={styles.title}>{label}</Text> : null}

                    <ScrollView>
                        {options.map((option) => {
                            const active = option.value === value;

                            return (
                                <Pressable
                                    key={String(option.value)}
                                    accessibilityRole="button"
                                    accessibilityState={{ selected: active }}
                                    onPress={() => {
                                        onChange(option.value);
                                        setOpen(false);
                                    }}
                                    style={({ pressed }) => [styles.option, (pressed || active) && styles.optionActive]}
                                >
                                    <Text style={styles.optionLabel}>{option.label}</Text>
                                    {active ? <Icon name="check" size="sm" color={theme.colors.link} /> : null}
                                </Pressable>
                            );
                        })}
                    </ScrollView>
                </View>
            </Modal>
        </Field>
    );
}

const makeStyles = (t: Theme) =>
    StyleSheet.create({
        trigger: {
            flexDirection: 'row',
            alignItems: 'center',
            gap: space[8],
        },
        value: {
            flex: 1,
        },
        placeholder: {
            color: t.colors.fgMuted,
        },
        disabled: {
            opacity: 0.65,
        },
        backdrop: {
            ...StyleSheet.absoluteFill,
            backgroundColor: t.colors.backdrop,
        },
        panel: {
            position: 'absolute',
            left: 0,
            right: 0,
            bottom: 0,
            maxHeight: '70%',
            paddingTop: space[16],
            backgroundColor: t.colors.surface,
            borderTopLeftRadius: radius.lg,
            borderTopRightRadius: radius.lg,
        },
        title: {
            ...typography.fieldLabel,
            color: t.colors.fgMuted,
            paddingHorizontal: space[16],
            paddingBottom: space[8],
        },
        option: {
            flexDirection: 'row',
            alignItems: 'center',
            justifyContent: 'space-between',
            paddingVertical: space[12],
            paddingHorizontal: space[16],
        },
        optionActive: {
            backgroundColor: t.colors.surfaceRaised,
        },
        optionLabel: {
            ...typography.body,
            color: t.colors.fg,
        },
    });
