import { ReactNode } from 'react';
import { Pressable, ScrollView, StyleProp, StyleSheet, Text, View, ViewStyle } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { fontFamily, fontSize, inkColors, palette, space, ThemeProvider, tracking } from '../theme';
import { LogoMark } from './LogoMark';

export interface SidebarProps {
    firm: string;
    tagline?: string;
    onBrandPress?: () => void;
    user?: string;
    role?: string;
    initials?: string;
    onAccountPress?: () => void;
    footerActions?: ReactNode;
    children: ReactNode;
    style?: StyleProp<ViewStyle>;
}

/** The ink navigation panel; it always renders in the dark theme, as on the web. */
export function Sidebar({
    firm,
    tagline,
    onBrandPress,
    user,
    role,
    initials,
    onAccountPress,
    footerActions,
    children,
    style,
}: SidebarProps) {
    const insets = useSafeAreaInsets();

    return (
        <ThemeProvider mode="dark">
            <View style={[styles.sidebar, { paddingTop: insets.top + space[16], paddingBottom: insets.bottom + space[12] }, style]}>
                <Pressable accessibilityRole="button" disabled={!onBrandPress} onPress={onBrandPress} style={styles.row}>
                    <LogoMark size="sm" initial={firm.slice(0, 1)} />

                    <View style={styles.text}>
                        <Text style={styles.firm} numberOfLines={1}>
                            {firm}
                        </Text>
                        {tagline ? (
                            <Text style={styles.tagline} numberOfLines={1}>
                                {tagline}
                            </Text>
                        ) : null}
                    </View>
                </Pressable>

                <ScrollView style={styles.nav} contentContainerStyle={styles.navContent}>
                    {children}
                </ScrollView>

                {user ? (
                    <View style={styles.footer}>
                        <Pressable
                            accessibilityRole="button"
                            accessibilityLabel="Your account"
                            disabled={!onAccountPress}
                            onPress={onAccountPress}
                            style={[styles.row, styles.account]}
                        >
                            <View style={styles.avatar}>
                                <Text style={styles.initials}>{initials}</Text>
                            </View>

                            <View style={styles.text}>
                                <Text style={styles.user} numberOfLines={1}>
                                    {user}
                                </Text>
                                {role ? (
                                    <Text style={styles.role} numberOfLines={1}>
                                        {role}
                                    </Text>
                                ) : null}
                            </View>
                        </Pressable>

                        {footerActions}
                    </View>
                ) : null}
            </View>
        </ThemeProvider>
    );
}

const styles = StyleSheet.create({
    sidebar: {
        flex: 1,
        backgroundColor: palette.ink900,
    },
    row: {
        flexDirection: 'row',
        alignItems: 'center',
        gap: space[8],
        paddingHorizontal: space[16],
    },
    text: {
        flexShrink: 1,
    },
    firm: {
        color: inkColors.onInk,
        fontFamily: fontFamily.sansSemibold,
        fontSize: fontSize.md,
    },
    tagline: {
        color: inkColors.onInkCaption,
        fontFamily: fontFamily.mono,
        fontSize: fontSize.xs2,
        letterSpacing: tracking.label,
    },
    nav: {
        flex: 1,
        marginTop: space[16],
    },
    navContent: {
        paddingHorizontal: space[8],
    },
    footer: {
        flexDirection: 'row',
        alignItems: 'center',
        gap: space[8],
        paddingTop: space[12],
        paddingRight: space[16],
        borderTopWidth: 1,
        borderTopColor: inkColors.borderOnInk,
    },
    account: {
        flex: 1,
    },
    avatar: {
        alignItems: 'center',
        justifyContent: 'center',
        width: 28,
        height: 28,
        borderRadius: 14,
        backgroundColor: palette.gold500,
    },
    initials: {
        color: palette.ink900,
        fontFamily: fontFamily.sansSemibold,
        fontSize: 11,
    },
    user: {
        color: inkColors.onInk,
        fontFamily: fontFamily.sansMedium,
        fontSize: fontSize.base,
    },
    role: {
        color: inkColors.onInkCaption,
        fontFamily: fontFamily.mono,
        fontSize: fontSize.xs,
    },
});
