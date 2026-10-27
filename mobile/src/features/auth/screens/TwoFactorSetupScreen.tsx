import React, { useState } from 'react';
import { Alert, ScrollView, StyleSheet, Switch, Text, View } from 'react-native';
import QRCode from 'react-native-qrcode-svg';
import Button from '@/components/Button';
import Card from '@/components/Card';
import Input from '@/components/Input';
import {
    disableTwoFactor,
    enableSms,
    enableTotp,
    startSmsSetup,
    startTotpSetup,
} from '@/api/endpoint/auth.api';
import { getApiErrorMessage } from '@/api/errors';
import { useAppDispatch, useAppSelector } from '@/hooks/useAppDispatch';
import { setUser } from '@/features/auth/store/authSlice';
import { deviceAuthAvailable } from '@/features/auth/services/deviceAuth';
import { getAppLockEnabled, setAppLockEnabled } from '@/features/security/appLockSettings';
import { TwoFactorMethod, User } from '@/models/index';
import { colors, spacing, typography } from '@/theme/index';

type Stage = 'choose' | 'totp-confirm' | 'sms-confirm' | 'disable';

/**
 * Security settings. Two-factor is set up, checked and switched off on the BACKEND (this screen only relays
 * what the user types). The authenticator secret is shown once, at setup, so it can be scanned; the app does
 * not keep it. Also holds the app-lock switch (fingerprint / face / PIN when returning to the app).
 */
export default function TwoFactorSetupScreen() {
    const dispatch = useAppDispatch();
    const user = useAppSelector(state => state.auth.user);
    const [stage, setStage] = useState<Stage>('choose');
    const [secret, setSecret] = useState('');
    const [uri, setUri] = useState('');
    const [phone, setPhone] = useState(user?.phoneNumber ?? '');
    const [code, setCode] = useState('');
    const [password, setPassword] = useState('');
    const [loading, setLoading] = useState(false);
    const [lockOn, setLockOn] = useState<boolean | null>(null);
    const [lockPossible, setLockPossible] = useState(true);

    React.useEffect(() => {
        getAppLockEnabled().then(setLockOn).catch((error) => { console.error('Failed to load app lock setting', error) });
        deviceAuthAvailable().then(setLockPossible).catch((error) => { console.error('Failed to check device authentication:', error) });
    }, []);

    if (!user) {
        return null;
    }

    const run = async (task: () => Promise<void>) => {
        setLoading(true);
        try {
            await task();
        } catch (e) {
            Alert.alert('Something went wrong', getApiErrorMessage(e, 'Please try again.'));
        } finally {
            setLoading(false);
        }
    };

    const done = (updated: User, message: string) => {
        dispatch(setUser(updated));
        setStage('choose');
        setCode('');
        setPassword('');
        setSecret('');
        Alert.alert('Done', message);
    };

    const startTotp = () =>
        run(async () => {
            const s = await startTotpSetup();
            setSecret(s.secret);
            setUri(s.otpauthUri);
            setCode('');
            setStage('totp-confirm');
        });

    const confirmTotp = () => run(async () => done(await enableTotp(code.trim()), 'Your account now requires an authenticator code at sign-in.'));

    const sendSms = () => run(async () => startSmsSetup(phone.trim()));

    const confirmSms = () => run(async () => done(await enableSms(code.trim()), 'Your account now requires an SMS code at sign-in.'));

    const turnOff = () => run(async () => done(await disableTwoFactor(password), 'Two-factor authentication is off.'));

    const toggleLock = async (value: boolean) => {
        await setAppLockEnabled(value);
        setLockOn(value);
    };

    if (stage === 'totp-confirm') {
        return (
            <ScrollView style={styles.container} contentContainerStyle={styles.content}>
                <Text style={styles.title}>Scan with your authenticator app</Text>
                <Card style={styles.qrCard}>
                    <QRCode value={uri} size={200} />
                </Card>
                <Text style={styles.label}>Can&apos;t scan? Enter this key manually:</Text>
                <Text selectable style={styles.manualKey}>{secret}</Text>
                <Input label="Code from app" value={code} onChangeText={setCode} keyboardType="number-pad" maxLength={6} />
                <Button label="Confirm & enable" onPress={confirmTotp} loading={loading} />
                <Button label="Cancel" variant="secondary" onPress={() => setStage('choose')} style={styles.gap} />
            </ScrollView>
        );
    }

    if (stage === 'sms-confirm') {
        return (
            <ScrollView style={styles.container} contentContainerStyle={styles.content}>
                <Text style={styles.title}>Set up SMS verification</Text>
                <Input label="Phone number" value={phone} onChangeText={setPhone} keyboardType="phone-pad" placeholder="082 123 4567" />
                <Button label="Send code" variant="secondary" onPress={sendSms} loading={loading} style={styles.gap} />
                <Input label="Verification code" value={code} onChangeText={setCode} keyboardType="number-pad" maxLength={6} textContentType="oneTimeCode" />
                <Button label="Confirm & enable" onPress={confirmSms} loading={loading} />
                <Button label="Cancel" variant="secondary" onPress={() => setStage('choose')} style={styles.gap} />
            </ScrollView>
        );
    }

    if (stage === 'disable') {
        return (
            <ScrollView style={styles.container} contentContainerStyle={styles.content}>
                <Text style={styles.title}>Turn off two-factor</Text>
                <Text style={styles.subtitle}>Enter your password to confirm.</Text>
                <Input label="Password" value={password} onChangeText={setPassword} secureTextEntry />
                <Button label="Turn off" variant="danger" onPress={turnOff} loading={loading} />
                <Button label="Cancel" variant="secondary" onPress={() => setStage('choose')} style={styles.gap} />
            </ScrollView>
        );
    }

    const method: TwoFactorMethod = user.twoFactorMethod ?? 'none';
    const methodLabel =
        method === 'totp'
            ? 'Authenticator app'
            : method === 'sms'
                ? 'SMS code'
                : 'Off';
    return (
        <ScrollView style={styles.container} contentContainerStyle={styles.content}>
            <Text style={styles.title}>Two-factor authentication</Text>
            <Text style={styles.subtitle}>
                Current method: <Text style={styles.current}>{methodLabel}</Text>
            </Text>

            <Card style={styles.card}>
                <Text style={styles.optionTitle}>Authenticator app</Text>
                <Text style={styles.optionBody}>Google Authenticator, Microsoft Authenticator, or any standard TOTP app. Works offline.</Text>
                <Button label={method === 'totp' ? 'Set up again' : 'Set up authenticator app'} onPress={startTotp} variant="secondary" loading={loading} />
            </Card>

            <Card style={styles.card}>
                <Text style={styles.optionTitle}>SMS code</Text>
                <Text style={styles.optionBody}>A 6-digit code sent by the firm&apos;s server to your phone at every sign-in. Needs a connection.</Text>
                <Button label={method === 'sms' ? 'Change phone number' : 'Set up SMS verification'} onPress={() => { setCode(''); setStage('sms-confirm'); }} variant="secondary" />
            </Card>

            {method !== 'none' ? <Button label="Turn off two-factor authentication" onPress={() => setStage('disable')} variant="danger" /> : null}

            <Text style={[styles.title, styles.sectionGap]}>App lock</Text>
            <Card style={styles.card}>
                <View style={styles.row}>
                    <Text style={styles.optionTitle}>Ask for fingerprint / face / PIN</Text>
                    <Switch value={Boolean(lockOn)} onValueChange={toggleLock} disabled={lockOn === null || !lockPossible} accessibilityLabel="App lock" />
                </View>
                <Text style={styles.optionBody}>
                    {lockPossible
                        ? 'LegalCMS locks itself when you leave it for a minute, and hides its contents in the app switcher.'
                        : 'Set a screen lock (PIN, pattern or biometrics) on this phone to use app lock.'}
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
    current: { fontWeight: '700', color: colors.navy },
    card: { gap: spacing.sm, marginBottom: spacing.md },
    optionTitle: { ...typography.body, fontWeight: '700', color: colors.textPrimary, flexShrink: 1 },
    optionBody: { ...typography.caption, color: colors.textSecondary },
    row: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: spacing.md },
    qrCard: { alignItems: 'center', padding: spacing.lg },
    label: { ...typography.caption, color: colors.textSecondary, marginTop: spacing.md },
    manualKey: { ...typography.body, fontWeight: '700', color: colors.navy, marginBottom: spacing.md, letterSpacing: 1 },
    gap: { marginTop: spacing.sm, marginBottom: spacing.md },
});
