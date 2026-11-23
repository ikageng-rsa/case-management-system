import React, { useEffect, useState } from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { useRouter } from 'expo-router';
import Button from '@/components/Button';
import Input from '@/components/Input';
import { useAppDispatch, useAppSelector } from '@/hooks/useAppDispatch';
import { cancelTwoFactor, clearPendingLogin, completeTwoFactor } from '@/features/auth/store/authSlice';
import { colors, spacing, typography } from '@/theme/index';
import { resendSignInCode } from '@/api/endpoint/auth.api';
import { getApiErrorMessage } from '@/api/errors';
import { notifyError, notifySuccess } from '@/utils/notify';

/** The server refuses a second code inside a minute; the button waits the same time instead of failing. */
const RESEND_WAIT_SECONDS = 60;

/**
 * Second step of every sign-in: the backend e-mailed a one-time code after the password was accepted. The app only
 * relays what the user types; no session token exists until the backend accepts the code.
 */
export default function TwoFactorChallengeScreen() {
    const router = useRouter();
    const dispatch = useAppDispatch();
    const challenge = useAppSelector(state => state.auth.challenge);
    const email = useAppSelector(state => state.auth.pendingEmail);

    const [code, setCode] = useState('');
    const [loading, setLoading] = useState(false);
    const [resending, setResending] = useState(false);
    const [wait, setWait] = useState(RESEND_WAIT_SECONDS);

    useEffect(() => {
        if (!challenge) {
            router.replace('/login');
        }
    }, [challenge, router]);

    useEffect(() => {
        if (wait <= 0) {
            return;
        }
        const timer = setTimeout(() => setWait(w => w - 1), 1000);
        return () => clearTimeout(timer);
    }, [wait]);

    const resend = async () => {
        if (!challenge) {
            return;
        }
        setResending(true);
        try {
            await resendSignInCode(challenge.token);
            setCode('');
            setWait(RESEND_WAIT_SECONDS);
            notifySuccess('New code sent', `Check ${email ?? 'your email'}.`);
        } catch (e) {
            setWait(RESEND_WAIT_SECONDS);
            notifyError('Could not send a new code', getApiErrorMessage(e, 'Please sign in again.'));
        } finally {
            setResending(false);
        }
    };

    const verify = async () => {
        if (code.trim().length < 6) {
            return;
        }
        setLoading(true);
        const result = await dispatch(completeTwoFactor({ code }));
        setLoading(false);
        if (completeTwoFactor.rejected.match(result)) {
            setCode('');
            notifyError('Could not verify', result.error.message ?? 'Check the code and try again.');
        } else {
            router.replace('/');
        }
    };

    const cancel = () => {
        clearPendingLogin();
        dispatch(cancelTwoFactor());
        router.replace('/login');
    };

    if (!challenge) {
        return null;
    }

    const minutes = challenge.expiresIn ? Math.max(1, Math.round(challenge.expiresIn / 60)) : null;

    return (
        <View style={styles.container}>
            <Text style={styles.title}>Check your email</Text>
            <Text style={styles.subtitle}>
                {`We sent a 6-digit code to ${email ?? 'your email address'}.`}
                {minutes ? ` It works for ${minutes} minutes.` : ''}
            </Text>

            <Input
                label="Verification code"
                value={code}
                onChangeText={setCode}
                keyboardType="number-pad"
                maxLength={6}
                textContentType="oneTimeCode"
                autoComplete="one-time-code"
            />
            <Button label="Verify" onPress={verify} loading={loading} />
            <Button
                label={wait > 0 ? `Send a new code (${wait}s)` : 'Send a new code'}
                variant="secondary"
                onPress={resend}
                loading={resending}
                disabled={wait > 0}
                style={styles.cancelButton}
            />
            <Text style={styles.hint}>If the sign-in has run out, go back and sign in again.</Text>
            <Button label="Back to sign in" variant="secondary" onPress={cancel} style={styles.cancelButton} />
        </View>
    );
}

const styles = StyleSheet.create({
    container: { flex: 1, backgroundColor: colors.background, padding: spacing.lg },
    title: { ...typography.h2, color: colors.navy, marginBottom: spacing.xs },
    subtitle: { ...typography.body, color: colors.textSecondary, marginBottom: spacing.lg },
    hint: { ...typography.caption, color: colors.textSecondary, marginTop: spacing.md },
    cancelButton: { marginTop: spacing.sm, borderColor: 'transparent' },
});
