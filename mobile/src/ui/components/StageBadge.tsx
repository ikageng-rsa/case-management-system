import { StyleProp, StyleSheet, Text, TextProps, TextStyle } from 'react-native';
import { alpha, fontFamily, fontSize, palette, radius, space } from '../theme';

export type StageTone = 'neutral' | 'info' | 'accent' | 'success' | 'danger';

const tints: Record<StageTone, { backgroundColor: string; color: string }> = {
    neutral: { backgroundColor: alpha(palette.tintSlate, 0.14), color: palette.slate600 },
    info: { backgroundColor: alpha(palette.tintTeal, 0.14), color: palette.teal700 },
    accent: { backgroundColor: alpha(palette.tintGold, 0.2), color: palette.gold900 },
    success: { backgroundColor: alpha(palette.tintGreen, 0.14), color: palette.green700 },
    danger: { backgroundColor: alpha(palette.tintRust, 0.14), color: palette.rust800 },
};

export interface StageBadgeProps extends Pick<TextProps, 'accessibilityLabel' | 'testID'> {
    children: string;
    tone?: StageTone;
    style?: StyleProp<TextStyle>;
}

export function StageBadge({ children, tone = 'neutral', style, ...rest }: StageBadgeProps) {
    return (
        <Text style={[styles.badge, tints[tone], style]} numberOfLines={1} {...rest}>
            {children}
        </Text>
    );
}

const styles = StyleSheet.create({
    badge: {
        alignSelf: 'flex-start',
        overflow: 'hidden',
        paddingVertical: 3,
        paddingHorizontal: space[8],
        borderRadius: radius.sm,
        fontFamily: fontFamily.sansMedium,
        fontSize: fontSize.sm,
        lineHeight: 13,
    },
});
