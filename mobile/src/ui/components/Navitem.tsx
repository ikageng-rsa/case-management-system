import { Pressable, StyleSheet, Text } from 'react-native';
import { alpha, fontFamily, fontSize, inkColors, palette, radius, space } from '../theme';
import { Icon, IconName } from './Icon';

export interface NavItemProps {
    label: string;
    icon?: IconName;
    count?: number | string;
    active?: boolean;
    onPress?: () => void;
}

export function NavItem({ label, icon, count, active = false, onPress }: NavItemProps) {
    return (
        <Pressable
            onPress={onPress}
            accessibilityRole="button"
            accessibilityState={{ selected: active }}
            style={({ pressed }) => [styles.item, pressed && styles.pressed, active && styles.active]}
        >
            {({ pressed }) => {
                const color = active || pressed ? inkColors.onInk : inkColors.onInkMuted;

                return (
                    <>
                        {icon ? <Icon name={icon} size="sm" color={color} /> : null}
                        <Text style={[styles.label, { color }]} numberOfLines={1}>
                            {label}
                        </Text>
                        {count !== undefined ? <Text style={styles.count}>{count}</Text> : null}
                    </>
                );
            }}
        </Pressable>
    );
}

const styles = StyleSheet.create({
    item: {
        flexDirection: 'row',
        alignItems: 'center',
        gap: space[10],
        marginVertical: 1,
        paddingVertical: space[8],
        paddingHorizontal: space[12],
        borderWidth: 2,
        borderColor: 'transparent',
        borderRadius: radius.base,
    },
    pressed: {
        backgroundColor: alpha(palette.white, 0.05),
    },
    active: {
        backgroundColor: alpha(palette.gold500, 0.16),
        borderColor: palette.gold500,
    },
    label: {
        flex: 1,
        fontFamily: fontFamily.sans,
        fontSize: fontSize.md,
    },
    count: {
        color: inkColors.onInkCount,
        fontFamily: fontFamily.monoMedium,
        fontSize: fontSize.xs,
    },
});
