import React, { useState } from 'react';
import { Alert, StyleSheet, Text, View } from 'react-native';
import Button from '@/components/Button';
import Input from '@/components/Input';
import { requestPasswordReset } from '@/api/endpoint/auth.api';
import { colors, spacing, typography } from '@/theme/index';

export default function ResetPasswordScreen() {
  const [email, setEmail] = useState('');
  const [loading, setLoading] = useState(false);

  const submit = async () => {
    if (!email) {
      return;
    }
    setLoading(true);
    try {
      await requestPasswordReset(email);
      Alert.alert('Check your inbox', 'If that email is registered, a reset link is on its way.');
    } catch {
      Alert.alert('Something went wrong', 'Please try again in a moment.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <View style={styles.container}>
      <Text style={styles.title}>Reset password</Text>
      <Input
        label="Email"
        value={email}
        onChangeText={setEmail}
        autoCapitalize="none"
        keyboardType="email-address"
      />
      <Button label="Send reset link" onPress={submit} loading={loading} />
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.background,
    padding: spacing.lg,
  },
  title: {
    ...typography.h2,
    color: colors.navy,
    marginBottom: spacing.lg,
  },
});
