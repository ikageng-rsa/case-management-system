import React, { useEffect, useState } from 'react';
import { Alert, StyleSheet, Text, View } from 'react-native';
import { useRouter } from 'expo-router';
import Button from '@/components/Button';
import Input from '@/components/Input';
import { useAppDispatch, useAppSelector } from '@/hooks/useAppDispatch';
import { cancelTwoFactor, confirmTwoFactor } from '@/features/auth/store/authSlice';
import { setTwoFactorPending } from '@/features/auth/services/twoFactorPending';
import { clearAuthToken } from '@/api/client';
import {
    getTwoFactorSettings,
    sendTwoFactorSmsChallenge,
    verifyTwoFactorChallenge,
} from '@/features/auth/services/twoFactorService';
import { TwoFactorSettings } from '@/models/index';
import { colors, spacing, typography } from '@/theme/index';

/** Shown after a correct password when the account has TOTP or SMS 2FA enabled. */
export default function TwoFactorChallengeScreen() {
    const router = useRouter();
    const dispatch = useAppDispatch();
    const pendingUser = useAppSelector(state => state.auth.pendingUser);

    const [settings, setSettings] = useState<TwoFactorSettings | null>(null);
    const [code, setCode] = useState('');
    const [loading, setLoading] = useState(false);
    const [smsSent, setSmsSent] = useState(false);

    useEffect(() => {
        if (!pendingUser) {
            router.replace('/login');
            return;
        }
        getTwoFactorSettings(pendingUser.id).then(setSettings);
    }, [pendingUser]);

    const sendSms = async () => {
        if (!pendingUser?.phoneNumber) {
            Alert.alert('No phone number', 'Add a phone number in your profile to use SMS 2FA.');
            return;
        }
        setLoading(true);
        const result = await sendTwoFactorSmsChallenge(pendingUser.id, pendingUser.phoneNumber);
        setLoading(false);
        setSmsSent(true);
        if (!result.delivered) {
            Alert.alert(
                'Could not send SMS',
                result.devCode ? `No SMS provider configured — dev code: ${result.devCode}` : result.deliveryError ?? 'Try again.',
            );
        }
    };

    const verify = async () => {
        if (!pendingUser || !settings) {
            return;
        }
        setLoading(true);
        try {
            const ok = await verifyTwoFactorChallenge(pendingUser.id, settings, code);
            if (ok) {
                await setTwoFactorPending(false);
                dispatch(confirmTwoFactor());
                router.replace('/');
            } else {
                Alert.alert('Incorrect code', 'Check the code and try again.');
            }
        } finally {
            setLoading(false);
        }
    };

    const cancel = async () => {
        await clearAuthToken();
        await setTwoFactorPending(false);
        dispatch(cancelTwoFactor());
        router.replace('/login');
    };

    if (!settings) {
        return null;
    }

    return (
        <View style={styles.container}>
            <Text style={styles.title}>Two-factor verification</Text>
            <Text style={styles.subtitle}>
                {settings.method === 'totp'
                    ? 'Enter the 6-digit code from your authenticator app.'
                    : 'Enter the 6-digit code sent to your phone.'}
            </Text>

            {settings.method === 'sms' ? (
                <Button
                    label={smsSent ? 'Resend code' : 'Send code'}
                    variant="secondary"
                    onPress={sendSms}
                    loading={loading}
                    style={styles.sendButton}
                />
            ) : null}

            <Input label="Verification code" value={code} onChangeText={setCode} keyboardType="number-pad" maxLength={6} />
            <Button label="Verify" onPress={verify} loading={loading} />
            <Button label="Cancel" variant="secondary" onPress={cancel} style={styles.cancelButton} />
        </View>
    );
}

const styles = StyleSheet.create({
    container: { flex: 1, backgroundColor: colors.background, padding: spacing.lg },
    title: { ...typography.h2, color: colors.navy, marginBottom: spacing.xs },
    subtitle: { ...typography.body, color: colors.textSecondary, marginBottom: spacing.lg },
    sendButton: { marginBottom: spacing.md },
    cancelButton: { marginTop: spacing.sm, borderColor: 'transparent' },
});
