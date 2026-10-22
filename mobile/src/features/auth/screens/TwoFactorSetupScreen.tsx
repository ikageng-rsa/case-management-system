import React, { useEffect, useState } from 'react';
import { Alert, ScrollView, StyleSheet, Text } from 'react-native';
import QRCode from 'react-native-qrcode-svg';
import Button from '@components/Button';
import Input from '@components/Input';
import Card from '@components/Card';
import { useAppSelector } from '@hooks/useAppDispatch';
import {
  buildTotpEnrollmentUri,
  disableTwoFactor,
  enableSmsTwoFactor,
  enableTotp,
  generateTotpSecret,
  getTwoFactorSettings,
  sendTwoFactorSmsChallenge,
  verifyTotpCode,
  verifyTwoFactorSmsChallenge,
} from '@/features/auth/services/twoFactorService';
import { TwoFactorMethod, TwoFactorSettings } from '@/models/index';
import { colors, spacing, typography } from '@/theme/index';

type SetupStage = 'choose' | 'totp-confirm' | 'sms-confirm';

/**
 * Lets a user turn on TOTP (scan with Google/Microsoft Authenticator) or SMS
 * OTP 2FA for their own account, or turn it off again. Reached from More ->
 * Client Profile (see ClientProfileScreen).
 */
export default function TwoFactorSetupScreen() {
  const user = useAppSelector(state => state.auth.user);
  const [settings, setSettings] = useState<TwoFactorSettings>({ method: 'none' });
  const [stage, setStage] = useState<SetupStage>('choose');
  const [pendingSecret, setPendingSecret] = useState('');
  const [phoneNumber, setPhoneNumber] = useState(user?.phoneNumber ?? '');
  const [code, setCode] = useState('');
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    if (user) {
      getTwoFactorSettings(user.id).then(setSettings);
    }
  }, [user]);

  if (!user) {
    return null;
  }

  const startTotpEnrollment = () => {
    setPendingSecret(generateTotpSecret());
    setCode('');
    setStage('totp-confirm');
  };

  const startSmsEnrollment = () => {
    setCode('');
    setStage('sms-confirm');
  };

  const confirmTotp = async () => {
    if (!verifyTotpCode(pendingSecret, code)) {
      Alert.alert('Incorrect code', 'Make sure your authenticator app shows the current code.');
      return;
    }
    await enableTotp(user.id, pendingSecret);
    setSettings({ method: 'totp', totpSecret: pendingSecret, enabledAt: new Date().toISOString() });
    setStage('choose');
    Alert.alert('TOTP enabled', 'Your account now requires an authenticator code at sign-in.');
  };

  const sendSmsCode = async () => {
    if (!phoneNumber) {
      Alert.alert('Phone number required', 'Enter the number to receive codes on.');
      return;
    }
    setLoading(true);
    const result = await sendTwoFactorSmsChallenge(user.id, phoneNumber);
    setLoading(false);
    if (!result.delivered) {
      Alert.alert(
        'Could not send SMS',
        result.devCode ? `No SMS provider configured — dev code: ${result.devCode}` : result.deliveryError ?? 'Try again.',
      );
    }
  };

  const confirmSms = async () => {
    const ok = await verifyTwoFactorSmsChallenge(user.id, code);
    if (!ok) {
      Alert.alert('Incorrect code', 'Request a new code and try again.');
      return;
    }
    await enableSmsTwoFactor(user.id, phoneNumber);
    setSettings({ method: 'sms', phoneNumber, enabledAt: new Date().toISOString() });
    setStage('choose');
    Alert.alert('SMS 2FA enabled', 'Your account now requires an SMS code at sign-in.');
  };

  const turnOff = async () => {
    await disableTwoFactor(user.id);
    setSettings({ method: 'none' });
  };

  if (stage === 'totp-confirm') {
    const uri = buildTotpEnrollmentUri(pendingSecret, user.email);
    return (
      <ScrollView style={styles.container} contentContainerStyle={styles.content}>
        <Text style={styles.title}>Scan with your authenticator app</Text>
        <Card style={styles.qrCard}>
          <QRCode value={uri} size={200} />
        </Card>
        <Text style={styles.manualKeyLabel}>Can&apos;t scan? Enter this key manually:</Text>
        <Text selectable style={styles.manualKey}>
          {pendingSecret}
        </Text>
        <Input label="Code from app" value={code} onChangeText={setCode} keyboardType="number-pad" maxLength={6} />
        <Button label="Confirm & enable" onPress={confirmTotp} />
        <Button label="Cancel" variant="secondary" onPress={() => setStage('choose')} style={styles.cancelButton} />
      </ScrollView>
    );
  }

  if (stage === 'sms-confirm') {
    return (
      <ScrollView style={styles.container} contentContainerStyle={styles.content}>
        <Text style={styles.title}>Set up SMS verification</Text>
        <Input label="Phone number" value={phoneNumber} onChangeText={setPhoneNumber} keyboardType="phone-pad" placeholder="082 123 4567" />
        <Button label="Send code" variant="secondary" onPress={sendSmsCode} loading={loading} style={styles.cancelButton} />
        <Input label="Verification code" value={code} onChangeText={setCode} keyboardType="number-pad" maxLength={6} />
        <Button label="Confirm & enable" onPress={confirmSms} />
        <Button label="Cancel" variant="secondary" onPress={() => setStage('choose')} style={styles.cancelButton} />
      </ScrollView>
    );
  }

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content}>
      <Text style={styles.title}>Two-factor authentication</Text>
      <Text style={styles.subtitle}>
        Current method: <Text style={styles.currentMethod}>{methodLabel(settings.method)}</Text>
      </Text>

      <Card style={styles.card}>
        <Text style={styles.optionTitle}>Authenticator app (TOTP)</Text>
        <Text style={styles.optionBody}>Google Authenticator, Microsoft Authenticator, or any standard TOTP app.</Text>
        <Button label="Set up authenticator app" onPress={startTotpEnrollment} variant="secondary" />
      </Card>

      <Card style={styles.card}>
        <Text style={styles.optionTitle}>SMS code</Text>
        <Text style={styles.optionBody}>A 6-digit code sent to your phone at every sign-in.</Text>
        <Button label="Set up SMS verification" onPress={startSmsEnrollment} variant="secondary" />
      </Card>

      {settings.method !== 'none' ? (
        <Button label="Turn off two-factor authentication" onPress={turnOff} variant="danger" />
      ) : null}
    </ScrollView>
  );
}

function methodLabel(method: TwoFactorMethod): string {
  if (method === 'totp') return 'Authenticator app';
  if (method === 'sms') return 'SMS code';
  return 'Off';
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.background },
  content: { padding: spacing.lg },
  title: { ...typography.h2, color: colors.navy, marginBottom: spacing.xs },
  subtitle: { ...typography.body, color: colors.textSecondary, marginBottom: spacing.lg },
  currentMethod: { fontWeight: '700', color: colors.navy },
  card: { gap: spacing.sm, marginBottom: spacing.md },
  optionTitle: { ...typography.body, fontWeight: '700', color: colors.textPrimary },
  optionBody: { ...typography.caption, color: colors.textSecondary },
  qrCard: { alignItems: 'center', padding: spacing.lg },
  manualKeyLabel: { ...typography.caption, color: colors.textSecondary, marginTop: spacing.md },
  manualKey: { ...typography.body, fontWeight: '700', color: colors.navy, marginBottom: spacing.md, letterSpacing: 1 },
  cancelButton: { marginTop: spacing.sm, marginBottom: spacing.md },
});
