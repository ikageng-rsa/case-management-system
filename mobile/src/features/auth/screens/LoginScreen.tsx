import React ,{useEffect, useState}from 'react';
import { KeyboardAvoidingView, Platform, StyleSheet, Text, View } from 'react-native';
import { useRouter } from 'expo-router';
import Button from '@/components/Button';
import Input from '@/components/Input';
import { colors, spacing, typography } from '@/theme/index';
import { useLogin } from '@/features/auth/hooks/useLogin';

export default function LoginScreen() {
  const router = useRouter();
  const { email, setEmail, password, setPassword, submit, loading, error } = useLogin();
  const [lockoutSeconds, setLockoutSeconds] = useState(0);

  //Start a 30-second localout when too many login attempts are detected.
  useEffect(()=>{
    if(error?.toLowerCase().includes('too many attempts')){
      setLockoutSeconds(30);
    }
  }, [error]);

  //Count down once per second
  useEffect(() => {
    if(lockoutSeconds <= 0) return;

    const timer = setTimeout(() =>{
      setLockoutSeconds(seconds => Math.max(0,seconds - 1));
    }, 1000);
    return () => clearTimeout(timer);
  }, [lockoutSeconds]);
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

      <Button
        label={lockoutSeconds > 0 ? `Try again in ${lockoutSeconds}s`: 'Sign In'}
        onPress={submit}
        loading={loading}
        disabled = {loading || lockoutSeconds > 0}
      />
      <Button
        label="Forgot password?"
        variant="secondary"
        onPress={() => router.push('/forgot-password')}
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
  demoHint: {
    ...typography.body,
    color: colors.textSecondary,
    textAlign: 'center',
    marginTop: spacing.sm,
  },
  secondaryButton: {
    marginTop: spacing.sm,
    borderColor: 'transparent',
  },
});

