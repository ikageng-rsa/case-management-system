import React, { useState } from 'react';
import { Alert, StyleSheet, Text, View } from 'react-native';
import Button from '@/components/Button';
import Input from '@/components/Input';
import { useRouter } from 'expo-router';
import { requestPasswordResetCode } from '@/api/endpoint/auth.api';
import { getApiErrorMessage } from '@/api/errors';
import { colors, spacing, typography } from '@/theme/index';


/**
 * Step 1 of 3 of the forgot-password flow : request a verification code
 * The must be entered (step 2) before a new password can be set
 * (step 3) - this is never a single-step "reset link" flow.
 */

export default function ForgotPasswordScreen() {
  const router = useRouter();
  const [email, setEmail] = useState('');
  const [loading, setLoading] = useState(false);

  const submit = async () => {
    if (!email) {
      Alert.alert('Email required', 'Enter the email address on your account.');
      return;
    }
    setLoading(true);
    try{
      //Backend generates the code and delivers it (e-mail/SMS). It answers the same way whether or not the
      //account exists, so this screen can't be used to find out who has an account
      await requestPasswordResetCode(email.trim());
      router.push({pathname: '/verify-reset-code', params: {email: email.trim()}});
    }catch(error){
        Alert.alert('Could not send code', getApiErrorMessage(error));
    }finally{
      setLoading(false);
    }
  }
    return (
      <View style={styles.container}>
        <Text style={styles.title}>Forgot password</Text>
        <Text style={styles.subtitle}>We&apos;ll send a 6-digit verification code to confirm it&apos;s you.</Text>
        <Input
          label="Email"
          value={email}
          onChangeText={setEmail}
          autoCapitalize="none"
          keyboardType="email-address"
          placeholder="you@firm.co.za"
        />
        <Button label="Send code" onPress={submit} loading={loading} />
      </View>
    )
  
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.background, padding: spacing.lg },
  title: { ...typography.h2, color: colors.navy, marginBottom: spacing.xs },
  subtitle: { ...typography.body, color: colors.textSecondary, marginBottom: spacing.lg },
});

