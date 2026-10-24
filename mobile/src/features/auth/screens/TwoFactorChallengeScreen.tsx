import React, { useEffect, useState } from 'react';
import { Alert, StyleSheet, Text, View } from 'react-native';
import { useRouter } from 'expo-router';
import Button from '@/components/Button';
import Input from '@/components/Input';
import { resendTwoFactorCode } from '@/api/endpoint/auth.api';
import { getApiErrorMessage } from '@/api/errors';
import { useAppDispatch, useAppSelector } from '@/hooks/useAppDispatch';
import { cancelTwoFactor, clearPendingLogin, completeTwoFactor } from '@/features/auth/store/authSlice';
import { colors, spacing, typography } from '@/theme/index';

/**
 * Shown after a correct password when the account has two-factor on. The BACKEND checks the code (and sent
 * the SMS, if that is the method); the app only relays what the user types. No session token exists until
 * the backend accepts it.
 */
export default function TwoFactorChallengeScreen() {
    const router = useRouter();
    const dispatch = useAppDispatch();
    const challenge = useAppSelector(state => state.auth.challenge);

    const [code, setCode] = useState('');
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        if (!challenge) {
            router.replace('/login');
        }
    }, [challenge, router]);

    const verify = async () => {
        if (code.trim().length < 6) {
            return;
        }
        setLoading(true);
        const result = await dispatch(completeTwoFactor({ code }));
        setLoading(false);
        if (completeTwoFactor.rejected.match(result)) {
            setCode('');
            Alert.alert('Could not verify', result.error.message ?? 'Check the code and try again.');
        } else {
            router.replace('/');
        }
    };

    const resend = async () => {
        if (!challenge) {
            return;
        }
        setLoading(true);
        try {
            await resendTwoFactorCode(challenge.token);
            Alert.alert('Code sent', 'A new code is on its way.');
        } catch (e) {
            Alert.alert('Could not send', getApiErrorMessage(e, 'Try again in a moment.'));
        } finally {
            setLoading(false);
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

    return (
        <View style={styles.container}>
            <Text style={styles.title}>Two-factor verification</Text>
            <Text style={styles.subtitle}>
                {challenge.method === 'totp'
                    ? 'Enter the 6-digit code from your authenticator app.'
                    : `Enter the 6-digit code we sent to ${challenge.maskedPhone ?? 'your phone'}.`}
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
            {challenge.method === 'sms' ? (
                <Button label="Send a new code" variant="secondary" onPress={resend} disabled={loading} style={styles.secondary} />
            ) : null}
            <Button label="Cancel" variant="secondary" onPress={cancel} style={styles.cancelButton} />
        </View>
    );
}

const styles = StyleSheet.create({
    container: { flex: 1, backgroundColor: colors.background, padding: spacing.lg },
    title: { ...typography.h2, color: colors.navy, marginBottom: spacing.xs },
    subtitle: { ...typography.body, color: colors.textSecondary, marginBottom: spacing.lg },
    secondary: { marginTop: spacing.sm },
    cancelButton: { marginTop: spacing.sm, borderColor: 'transparent' },
});
