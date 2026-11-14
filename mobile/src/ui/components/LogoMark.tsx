import { StyleProp, StyleSheet, Text, View, ViewStyle } from 'react-native';
import { fontFamily, palette, radius } from '../theme';

export interface LogoMarkProps {
    initial: string;
    size?: 'sm';
    style?: StyleProp<ViewStyle>;
}

export function LogoMark({ initial, size, style }: LogoMarkProps) {
    const small = size === 'sm';

    return (
        <View style={[styles.mark, small && styles.markSm, style]}>
            <Text style={[styles.initial, small && styles.initialSm]}>{initial}</Text>
        </View>
    );
}

const styles = StyleSheet.create({
    mark: {
        alignItems: 'center',
        justifyContent: 'center',
        width: 44,
        height: 44,
        borderWidth: 1,
        borderColor: palette.gold500,
        borderRadius: radius.sm,
    },
    markSm: {
        width: 25,
        height: 25,
    },
    initial: {
        color: palette.gold500,
        fontFamily: fontFamily.displaySemibold,
        fontSize: 19,
        lineHeight: 22,
        includeFontPadding: false,
    },
    initialSm: {
        fontSize: 15,
        lineHeight: 18,
    },
});
