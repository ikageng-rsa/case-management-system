import React, { PropsWithChildren, useCallback, useEffect, useRef, useState } from 'react';
import { AppState, StyleSheet, Text, View } from 'react-native';
import * as ScreenCapture from 'expo-screen-capture';
import Button from '@/components/Button';
import { useAppDispatch, useAppSelector } from '@/hooks/useAppDispatch';
import { logout } from '@/features/auth/store/authSlice';
import { deviceAuthAvailable, requireDeviceAuth } from '@/features/auth/services/deviceAuth';
import { getAppLockEnabled } from '@/features/security/appLockSettings';
import { shouldLock } from '@/features/security/lockPolicy';
import { colors, spacing, typography } from '@/theme/index';

const ALLOW_SCREENSHOTS = process.env.EXPO_PUBLIC_ALLOW_SCREENSHOTS === 'true';

/**
 * Phone-level protection for client data, active only while someone is signed in:
 *  - screenshots and screen recordings are blocked (Android FLAG_SECURE; iOS hides the content while recording),
 *  - when the app is not in the foreground a plain cover replaces the screen, so the app switcher shows nothing,
 *  - after a minute out of sight (or on a cold start with a saved session) the app asks for fingerprint / face / PIN.
 * A fresh sign-in is not followed by an unlock prompt — the person just proved who they are.
 */
export default function DeviceSecurityProvider({ children }: PropsWithChildren) {
    const dispatch = useAppDispatch();
    const signedIn = useAppSelector(state => state.auth.user !== null);

    const [covered, setCovered] = useState(false);
    const [locked, setLocked] = useState(false);
    const [failed, setFailed] = useState(false);
    const backgroundedAt = useRef<number | null>(null);
    const prompting = useRef(false);
    const startedSignedIn = useRef(signedIn);

    const unlock = useCallback(async () => {
        if (prompting.current) {
            return;
        }
        prompting.current = true;
        setFailed(false);
        const ok = await requireDeviceAuth('Unlock LegalCMS');
        // The system prompt itself briefly takes focus from the app; don't treat that as "left the app".
        backgroundedAt.current = null;
        prompting.current = false;
        if (ok) {
            setLocked(false);
        } else {
            setFailed(true);
        }
    }, []);

    const lockIfEnabled = useCallback(async () => {
        if ((await getAppLockEnabled()) && (await deviceAuthAvailable())) {
            setLocked(true);
            unlock();
        }
    }, [unlock]);

    // Cold start with a saved session: lock before showing anything.
    useEffect(() => {
        if (startedSignedIn.current) {
            lockIfEnabled();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    useEffect(() => {
        if (!signedIn) {
            // Signing out must always clear the lock and the privacy cover.
            /* eslint-disable react-hooks/set-state-in-effect */
            setLocked(false);
            setCovered(false);
            /* eslint-enable react-hooks/set-state-in-effect */
            return;
        }
        const sub = AppState.addEventListener('change', state => {
            if (prompting.current) {
                return;
            }
            if (state === 'active') {
                setCovered(false);
                if (shouldLock(backgroundedAt.current, Date.now())) {
                    lockIfEnabled();
                }
                backgroundedAt.current = null;
            } else {
                setCovered(true);
                backgroundedAt.current = backgroundedAt.current ?? Date.now();
            }
        });
        return () => sub.remove();
    }, [signedIn, lockIfEnabled]);

    useEffect(() => {
        if (ALLOW_SCREENSHOTS) {
            return;
        }
        (signedIn ? ScreenCapture.preventScreenCaptureAsync() : ScreenCapture.allowScreenCaptureAsync()).catch(() => { });
    }, [signedIn]);

    const hidden = signedIn && (locked || covered);

    return (
        <View style={styles.root}>
            {children}
            {hidden ? (
                <View style={styles.cover} testID="privacy-cover" accessibilityViewIsModal>
                    <Text style={styles.brand}>LegalCMS</Text>
                    {locked ? (
                        <View style={styles.actions}>
                            <Text style={styles.hint}>{failed ? 'Verification was not completed.' : 'Locked'}</Text>
                            <Button label="Unlock" onPress={unlock} />
                            <Button label="Sign out" variant="secondary" onPress={() => dispatch(logout())} style={styles.signOut} />
                        </View>
                    ) : null}
                </View>
            ) : null}
        </View>
    );
}

const styles = StyleSheet.create({
    root: { flex: 1 },
    cover: {
        position: 'absolute',
        top: 0,
        left: 0,
        right: 0,
        bottom: 0,
        zIndex: 1000,
        elevation: 1000,
        backgroundColor: colors.navyDark,
        alignItems: 'center',
        justifyContent: 'center',
        padding: spacing.lg,
    },
    brand: { ...typography.h1, color: colors.white, marginBottom: spacing.xl },
    actions: { alignSelf: 'stretch', gap: spacing.sm },
    hint: { ...typography.body, color: colors.white, textAlign: 'center', marginBottom: spacing.sm },
    signOut: { marginTop: spacing.xs },
});
