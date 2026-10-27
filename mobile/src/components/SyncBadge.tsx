import React from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { colors, radius, spacing, typography } from '@/theme/index';

/** Marks a record that was saved on this device but hasn't reached the server yet. */
export default function SyncBadge({ state }:Readonly< { state?: 'pending' | 'failed' }>) {
    if (!state) {
        return null;
    }
    const failed = state === 'failed';
    return (
        <View
            style={[styles.badge, { backgroundColor: failed ? colors.redTint : colors.goldTint }]}
            accessibilityLabel={failed ? 'Could not sync' : 'Waiting to sync'}
            testID="sync-badge"
        >
            <Text style={[styles.text, { color: failed ? colors.red : colors.gold }]}>
                {failed ? 'Sync failed' : 'Saved on device · waiting to sync'}
            </Text>
        </View>
    );
}

const styles = StyleSheet.create({
    badge: { paddingHorizontal: spacing.sm, paddingVertical: 2, borderRadius: radius.sm, alignSelf: 'flex-start' },
    text: { ...typography.caption, fontWeight: '700' },
});
