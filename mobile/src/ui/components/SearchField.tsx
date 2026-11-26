import { Ref, useState } from 'react';
import { StyleProp, StyleSheet, Text, TextInput, TextInputProps, View, ViewStyle } from 'react-native';
import { fontFamily, fontSize, radius, space, Theme, useStyles, useTheme } from '../theme';
import { Icon } from './Icon';

export interface SearchFieldProps extends TextInputProps {
    shortcut?: string;
    containerStyle?: StyleProp<ViewStyle>;
    ref?: Ref<React.ComponentRef<typeof TextInput>>;
}

export function SearchField({
    shortcut,
    containerStyle,
    style,
    placeholder = 'Search…',
    onFocus,
    onBlur,
    ref,
    ...props
}: SearchFieldProps) {
    const theme = useTheme();
    const styles = useStyles(makeStyles);
    const [focused, setFocused] = useState(false);

    return (
        <View style={[styles.field, focused && styles.focused, containerStyle]}>
            <Icon name="search" size="sm" color={theme.colors.fgMuted} />

            <TextInput
                ref={ref}
                accessibilityRole="search"
                placeholder={placeholder}
                placeholderTextColor={theme.colors.fgMuted}
                selectionColor={theme.colors.accent}
                returnKeyType="search"
                clearButtonMode="while-editing"
                autoCorrect={false}
                {...props}
                onFocus={(event) => {
                    setFocused(true);
                    onFocus?.(event);
                }}
                onBlur={(event) => {
                    setFocused(false);
                    onBlur?.(event);
                }}
                style={[styles.input, style]}
            />

            {shortcut ? <Text style={styles.shortcut}>{shortcut}</Text> : null}
        </View>
    );
}

const makeStyles = (t: Theme) =>
    StyleSheet.create({
        field: {
            flexDirection: 'row',
            alignItems: 'center',
            gap: space[8],
            height: 36,
            paddingHorizontal: space[12],
            backgroundColor: t.colors.bg,
            borderWidth: 1,
            borderColor: t.colors.lineStrong,
            borderRadius: radius.md,
        },
        focused: {
            borderColor: t.colors.accent,
        },
        input: {
            flex: 1,
            minWidth: 0,
            padding: 0,
            color: t.colors.fg,
            fontFamily: fontFamily.sans,
            fontSize: fontSize.md,
        },
        shortcut: {
            color: t.colors.fgSubtle,
            fontFamily: fontFamily.monoMedium,
            fontSize: fontSize.xs2,
        },
    });
