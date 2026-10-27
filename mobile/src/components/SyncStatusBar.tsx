import React from 'react';
import { Pressable, StyleSheet, Text } from 'react-native';
import { useRouter } from 'expo-router';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useNetInfo } from '@/hooks/useNetInfo';
import { useSyncStatus } from '@/hooks/useSyncStatus';
import { colors, spacing, typography } from '@/theme/index';

/**
 * Shown while online whenever offline work is still being sent, or needs the user's attention
 * (a change the server rejected, or a saved sign-in that has expired). Tapping opens the Sync screen.
 */
export default function SyncStatusBar() {
    const router = useRouter();
    const { isOnline } = useNetInfo();
    const { pending, failed, blocked, syncing, authRequired } = useSyncStatus();

    if (!isOnline) {
        return null; // the offline banner covers this case
    }
    const attention = failed + blocked;
    let message: string | null = null;
    let tone: 'info' | 'warn' = 'info';
    if (authRequired && pending > 0) {
        message = `${pending} change${pending === 1 ? '' : 's'} can't sync — sign out and sign in again`;
        tone = 'warn';
    } else if (attention > 0) {
        message = `${attention} change${attention === 1 ? '' : 's'} need${attention === 1 ? 's' : ''} your attention`;
        tone = 'warn';
    } else if (syncing || pending > 0) {
        message = `Syncing ${pending} change${pending === 1 ? '' : 's'}…`;
    }
    if (!message) {
        return null;
    }

    return (
        // Claims the top safe-area inset only while visible (an empty SafeAreaView would push the app down).
        <SafeAreaView edges={['top']} style={[styles.safe, tone === 'warn' ? styles.warn : styles.info]}>
            <Pressable
                onPress={() => router.push('./more/sync')}
                style={styles.bar}
                accessibilityRole="button"
                testID="sync-status-bar"
            >
                <Text style={styles.text}>{message}</Text>
            </Pressable>
        </SafeAreaView>
    );
}

const styles = StyleSheet.create({
    safe: { flex: 0 },
    bar: { paddingVertical: spacing.xs, paddingHorizontal: spacing.md },
    info: { backgroundColor: colors.navy },
    warn: { backgroundColor: colors.burgundy },
    text: { ...typography.captionBold, color: colors.white, textAlign: 'center' },
});
