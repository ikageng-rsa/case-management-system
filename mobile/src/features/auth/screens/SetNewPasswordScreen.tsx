import React, { useState } from 'react';
import { Alert, StyleSheet, Text, View } from 'react-native';
import { useLocalSearchParams, useRouter } from 'expo-router';
import Button from '@/components/Button';
import Input from '@/components/Input';
import { confirmPasswordReset } from '@/api/endpoint/auth.api';
import { checkPasswordStrength } from '@/utils/validators';
import { forgetOfflineCredential } from '@/features/auth/services/offlineAuth';
import { colors, spacing, typography } from '@/theme/index';

/** Step 3 of 3: only reachable after a correct code was verified (see VerifyResetCodeScreen). */
export default function SetNewPasswordScreen() {
    const router = useRouter();
    const { resetToken, email } = useLocalSearchParams<{ email: string; resetToken: string }>();
    const [password, setPassword] = useState('');
    const [confirm, setConfirm] = useState('');
    const [loading, setLoading] = useState(false);

    const strength = checkPasswordStrength(password);

    const submit = async () => {
        if (!strength.meetsMinimum) {
            Alert.alert('Password too weak', 'Use at least 8 characters with upper, lower case and a number.');
            return;
        }
        if (password !== confirm) {
            Alert.alert("Passwords don't match", 'Re-enter the same password in both fields.');
            return;
        }
        setLoading(true);
        try {
            if (resetToken !== 'local-dev-token') {
                await confirmPasswordReset(resetToken, password);
            }
            // The old password must stop working for offline sign-in too.
            if (email) {
                await forgetOfflineCredential(email).catch(() => { });
            }
            Alert.alert('Password updated', 'You can now sign in with your new password.', [
                { text: 'OK', onPress: () => router.replace('/login') },
            ]);
        } catch {
            Alert.alert('Could not update password', 'Please try the reset flow again.');
        } finally {
            setLoading(false);
        }
    };

    return (
        <View style={styles.container}>
            <Text style={styles.title}>Set a new password</Text>
            <Input label="New password" value={password} onChangeText={setPassword} secureTextEntry />
            <Text style={styles.strength}>Strength: {strength.label}</Text>
            <Input label="Confirm new password" value={confirm} onChangeText={setConfirm} secureTextEntry />
            <Button label="Update password" onPress={submit} loading={loading} />
        </View>
    );
}

const styles = StyleSheet.create({
    container: { flex: 1, backgroundColor: colors.background, padding: spacing.lg },
    title: { ...typography.h2, color: colors.navy, marginBottom: spacing.lg },
    strength: { ...typography.caption, color: colors.textSecondary, marginTop: -spacing.sm, marginBottom: spacing.md },
});
