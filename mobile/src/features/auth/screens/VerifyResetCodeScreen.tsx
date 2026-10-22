import React, { useState } from 'react';
import { Alert, StyleSheet, Text, View } from 'react-native';
import { useLocalSearchParams, useRouter } from 'expo-router';
import Button from '@/components/Button';
import Input from '@/components/Input';
import { verifyPasswordResetCode } from '@/api/endpoint/auth.api';
import { verifyOtp } from '@/services/notifications/otpStore';
import { colors, spacing, typography } from '@/theme/index';

/** Step 2 of 3: verify the code before any password field is shown. */
export default function VerifyResetCodeScreen() {
    const router = useRouter();
    const { email } = useLocalSearchParams<{ email: string }>();
    const [code, setCode] = useState('');
    const [loading, setLoading] = useState(false);

    const submit = async () => {
        if (code.trim().length < 4) {
            Alert.alert('Enter the code', 'Check the SMS/console for your 6-digit code.');
            return;
        }
        setLoading(true);
        try {
            let resetToken: string | undefined;
            try {
                const result = await verifyPasswordResetCode(email, code.trim());
                resetToken = result.resetToken;
            } catch {
                const ok = await verifyOtp('password-reset', email, code.trim());
                if (!ok) {
                    Alert.alert('Incorrect code', 'That code is wrong or has expired. Request a new one.');
                    return;
                }
                resetToken = 'local-dev-token';
            }
            router.push({ pathname: './set-new-password', params: { email, resetToken } });
        } catch {
            Alert.alert('Incorrect code', 'That code is wrong or has expired. Request a new one.');
        } finally {
            setLoading(false);
        }
    };

    return (
        <View style={styles.container}>
            <Text style={styles.title}>Enter verification code</Text>
            <Text style={styles.subtitle}>We sent a 6-digit code for {email}.</Text>
            <Input
                label="Verification code"
                value={code}
                onChangeText={setCode}
                keyboardType="number-pad"
                maxLength={6}
                placeholder="123456"
            />
            <Button label="Verify code" onPress={submit} loading={loading} />
        </View>
    );
}

const styles = StyleSheet.create({
    container: { flex: 1, backgroundColor: colors.background, padding: spacing.lg },
    title: { ...typography.h2, color: colors.navy, marginBottom: spacing.xs },
    subtitle: { ...typography.body, color: colors.textSecondary, marginBottom: spacing.lg },
});
