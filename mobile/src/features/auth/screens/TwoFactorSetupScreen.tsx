import React, { useState } from 'react';
import { ScrollView, StyleSheet, Switch, Text, View } from 'react-native';
import Card from '@/components/Card';
import { useAppSelector } from '@/hooks/useAppDispatch';
import { deviceAuthAvailable, requireDeviceAuth } from '@/features/auth/services/deviceAuth';
import { getAppLockEnabled, setAppLockEnabled } from '@/features/security/appLockSettings';
import { colors, fontFamily, spacing, typography } from '@/theme/index';
import { notifyWarning } from '@/utils/notify';

/**
 * Security settings. Every sign-in is confirmed with a code the firm's server e-mails (nothing to set up here).
 * This screen holds the app-lock switch (fingerprint / face / PIN when returning to the app).
 */
export default function TwoFactorSetupScreen() {
  const user = useAppSelector(state => state.auth.user);
  const [lockOn, setLockOn] = useState<boolean | null>(null);
  const [lockPossible, setLockPossible] = useState(true);

  React.useEffect(() => {
    // If either lookup fails the switch must still be usable (it used to stay disabled forever).
    getAppLockEnabled().then(setLockOn).catch(() => setLockOn(true));
    deviceAuthAvailable().then(setLockPossible).catch(() => setLockPossible(false));
  }, []);

  if (!user) {
    return null;
  }

  const toggleLock = async (value: boolean) => {
    // Re-check on every tap: the phone's screen lock may have been set up since this screen opened.
    const possible = await deviceAuthAvailable();
    setLockPossible(possible);
    if (!possible) {
      notifyWarning(
        'Screen lock needed',
        'Set a PIN, pattern, password, fingerprint or face unlock in your phone settings (Security), then come back and switch app lock on.',
      );
      return;
    }
    // Switching it on or off is itself a protected action: the owner must pass the lock first.
    if (!(await requireDeviceAuth(value ? 'Confirm to turn on app lock' : 'Confirm to turn off app lock'))) {
      return;
    }
    await setAppLockEnabled(value);
    setLockOn(value);
  };

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content}>
      <Text style={styles.title}>Sign-in</Text>
      <Card style={styles.card}>
        <Text style={styles.optionTitle}>Email code at every sign-in</Text>
        <Text style={styles.optionBody}>{`After your password, the firm's server sends a 6-digit code to ${user.email}.`}</Text>
      </Card>

      <Text style={[styles.title, styles.sectionGap]}>App lock</Text>
      <Card style={styles.card}>
        <View style={styles.row}>
          <Text style={styles.optionTitle}>Ask for fingerprint / face / PIN</Text>
          <Switch value={Boolean(lockOn) && lockPossible} onValueChange={toggleLock} disabled={lockOn === null} accessibilityLabel="App lock" />
        </View>
        <Text style={styles.optionBody}>
          {lockPossible
            ? 'LegalCMS locks itself when you leave it for a minute, and hides its contents in the app switcher.'
            : 'Your phone has no screen lock yet. Set a PIN, pattern, password or biometrics in your phone settings, then tap the switch again.'}
        </Text>
      </Card>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.background },
  content: { padding: spacing.lg },
  title: { ...typography.h2, color: colors.navy, marginBottom: spacing.xs },
  sectionGap: { marginTop: spacing.xl },
  subtitle: { ...typography.body, color: colors.textSecondary, marginBottom: spacing.lg },
  current: { fontFamily: fontFamily.sansSemibold, color: colors.navy },
  card: { gap: spacing.sm, marginBottom: spacing.md },
  optionTitle: { ...typography.body, fontFamily: fontFamily.sansSemibold, color: colors.textPrimary, flexShrink: 1 },
  optionBody: { ...typography.caption, color: colors.textSecondary },
  row: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: spacing.md },
  qrCard: { alignItems: 'center', padding: spacing.lg },
  label: { ...typography.caption, color: colors.textSecondary, marginTop: spacing.md },
  manualKey: { ...typography.body, fontFamily: fontFamily.sansSemibold, color: colors.navy, marginBottom: spacing.md, letterSpacing: 1 },
  gap: { marginTop: spacing.sm, marginBottom: spacing.md },
});
