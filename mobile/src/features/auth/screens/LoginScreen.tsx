import React from 'react';
import { KeyboardAvoidingView, Platform, StyleSheet, Text, View } from 'react-native';
import { useNavigation } from '@react-navigation/native';
import Button from '@/components/Button';
import Input from '@/components/Input';
import { colors, spacing, typography } from '@/theme/index';
import { useLogin } from '@/features/auth/hooks/useLogin';

export default function LoginScreen() {
  const navigation = useNavigation<any>();
  const { email, setEmail, password, setPassword, submit, loading, error } = useLogin();

  return (
    <KeyboardAvoidingView style={styles.container} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
      <View style={styles.header}>
        <Text style={styles.title}>LegalCMS</Text>
        <Text style={styles.subtitle}>Sign in to your firm account</Text>
      </View>

      <Input
        label="Email"
        value={email}
        onChangeText={setEmail}
        autoCapitalize="none"
        keyboardType="email-address"
        placeholder="you@firm.co.za"
      />
      <Input
        label="Password"
        value={password}
        onChangeText={setPassword}
        secureTextEntry
        placeholder="••••••••"
      />

      {error ? <Text style={styles.errorText}>{error}</Text> : null}

      <Button label="Sign In" onPress={submit} loading={loading} />

      <Button
        label="Forgot password?"
        variant="secondary"
        onPress={() => navigation.navigate('ResetPassword')}
        style={styles.secondaryButton}
      />
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.background,
    justifyContent: 'center',
    paddingHorizontal: spacing.lg,
  },
  header: {
    marginBottom: spacing.xl,
    alignItems: 'center',
  },
  title: {
    ...typography.h1,
    color: colors.navy,
  },
  subtitle: {
    ...typography.body,
    color: colors.textSecondary,
    marginTop: spacing.xs,
  },
  errorText: {
    color: colors.danger,
    marginBottom: spacing.md,
    textAlign: 'center',
  },
  secondaryButton: {
    marginTop: spacing.sm,
    borderColor: 'transparent',
  },
});
