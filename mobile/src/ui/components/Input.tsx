import React, { Ref, useRef, useState } from 'react';
import { StyleProp, StyleSheet, TextInput, TextInputProps, ViewStyle } from 'react-native';
import { useTheme } from '../theme';
import { Field, useFieldStyles } from './Field';

export interface InputProps extends TextInputProps {
    label?: string;
    before?: string;
    hint?: string;
    error?: string;
    tone?: 'dark';
    containerStyle?: StyleProp<ViewStyle>;
    ref?: Ref<React.ComponentRef<typeof TextInput>>;
}

export function Input({
    label,
    before,
    hint,
    error,
    tone,
    containerStyle,
    style,
    multiline,
    onFocus,
    onBlur,
    ref,
    ...props
}: InputProps) {
    const theme = useTheme();
    const field = useFieldStyles();
    const [focused, setFocused] = useState(false);
    const dark = tone === 'dark';
    return (
        <Field label={label} before={before} hint={hint} error={error} tone={tone} style={containerStyle}>
            <TextInput
                ref={ref}
                accessibilityLabel={label}
                placeholderTextColor={dark ? theme.colors.onInkCaption : theme.colors.fgMuted}
                selectionColor={theme.colors.accent}
                {...props}
                multiline={multiline}
                onFocus={(event) => {
                    setFocused(true);
                    onFocus?.(event);
                }}
                onBlur={(event) => {
                    setFocused(false);
                    onBlur?.(event);
                }}
                style={[
                    field.box,
                    field.text,
                    dark && [field.boxDark, field.textDark],
                    multiline && styles.multiline,
                    focused && (dark ? field.focusedDark : field.focused),
                    Boolean(error) && field.invalid,
                    style,
                ]}
            />
        </Field>
    );
}

const styles = StyleSheet.create({
    multiline: {
        minHeight: 64,
        textAlignVertical: 'top',
    },
});
