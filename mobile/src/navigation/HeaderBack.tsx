
import React from 'react';
import { Pressable, StyleSheet } from 'react-native';
import type { NativeStackNavigationOptions } from '@react-navigation/native-stack';
import { useRouter, type Href } from 'expo-router';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { colors } from '@/theme/index';

/**
 * Goes back one screen, or to `fallback` when there is nothing to go back to.
 * This handles screens opened directly from notifications, links, or replacements.
 */
export function useGoBack(fallback: Href): () => void {
    const router = useRouter();

    return () => {
        if (router.canGoBack()) {
            router.back();
        } else {
            router.replace(fallback);
        }
    };
}

/**
 * Reusable back button for screen headers.
 */
export function HeaderBack({ fallback }: { fallback: Href }) {
    const goBack = useGoBack(fallback);

    return (
        <Pressable
            accessibilityRole="button"
            accessibilityLabel="Go back"
            hitSlop={12}
            onPress={goBack}
            style={styles.button}
        >
            <MaterialCommunityIcons
                name="arrow-left"
                size={24}
                color={colors.white}
            />
        </Pressable>
    );
}

/**
 * Screen options that provide a back arrow with a fallback route.
 *
 * Usage: options={backTo('/diary')}
 */
export const backTo = (
    fallback: Href
): Pick<NativeStackNavigationOptions, 'headerLeft'> => ({
    headerLeft: () => <HeaderBack fallback={fallback} />,
});

const styles = StyleSheet.create({
    button: {
        paddingHorizontal: 8,
        paddingVertical: 4,
    },
});
