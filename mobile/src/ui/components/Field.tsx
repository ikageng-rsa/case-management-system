import { ReactNode } from 'react';
import { StyleProp, StyleSheet, Text, View, ViewStyle } from 'react-native';
import { alpha, fontFamily, fontSize, palette, radius, space, Theme, typography, useStyles } from '../theme';

export interface FieldProps {
    label?: string;
    before?: string;
    hint?: string;
    error?: string;
    tone?: 'dark';
    style?: StyleProp<ViewStyle>;
    children: ReactNode;
}

/** The label, hints and error around a form control. */
export function Field({ label, before, hint, error, tone, style, children }: FieldProps) {
    const styles = useStyles(makeStyles);

    return (
        <View style={[styles.field, style]}>
            {label ? <Text style={[styles.label, tone === 'dark' && styles.labelDark]}>{label}</Text> : null}
            {before ? <Text style={[styles.hint, styles.before]}>{before}</Text> : null}

            {children}

            {error ? <Text style={styles.error}>{error}</Text> : null}
            {!error && hint ? <Text style={styles.hint}>{hint}</Text> : null}
        </View>
    );
}

/** The bordered box shared by Input, Select and MatterPicker. */
export function useFieldStyles() {
    return useStyles(makeFieldStyles);
}

const makeStyles = (t: Theme) =>
    StyleSheet.create({
        field: {
            marginBottom: space[16],
        },
        label: {
            ...typography.fieldLabel,
            color: t.colors.fgMuted,
            marginBottom: space[8],
        },
        labelDark: {
            color: alpha(palette.white, 0.45),
        },
        before: {
            marginTop: 0,
            marginBottom: space[8],
        },
        hint: {
            ...typography.small,
            color: t.colors.fgMuted,
            marginTop: space[4],
        },
        error: {
            ...typography.small,
            color: t.tones.danger.bg,
            marginTop: space[4],
        },
    });

const makeFieldStyles = (t: Theme) =>
    StyleSheet.create({
        box: {
            minHeight: 40,
            paddingHorizontal: space[12],
            paddingVertical: space[8],
            borderWidth: 1,
            borderColor: t.colors.lineStrong,
            borderRadius: radius.md,
            backgroundColor: t.colors.surfaceRaised,
        },
        boxDark: {
            backgroundColor: alpha(palette.white, 0.05),
            borderColor: t.colors.borderOnInkStrong,
        },
        text: {
            color: t.colors.fg,
            fontFamily: fontFamily.sans,
            fontSize: fontSize.lg,
        },
        textDark: {
            color: t.colors.onInk,
        },
        focused: {
            borderColor: t.colors.accent,
        },
        focusedDark: {
            borderColor: t.colors.accent,
            backgroundColor: alpha(palette.white, 0.08),
        },
        invalid: {
            borderColor: t.tones.danger.bg,
        },
    });
