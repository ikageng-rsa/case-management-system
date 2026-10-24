import React, { useCallback, useEffect, useState } from 'react';
import { Alert, ScrollView, StyleSheet, Switch, Text, View } from 'react-native';
import Button from '@/components/Button';
import Card from '@/components/Card';
import Input from '@/components/Input';
import LoadingSpinner from '@/components/LoadingSpinner';
import { fetchNotificationPreferences, updateNotificationPreferences } from '@/api/endpoint/notifications.api';
import { getApiErrorMessage } from '@/api/errors';
import { NotificationChannelKey, NotificationPreferences, NotificationType } from '@/models/index';
import { colors, spacing, typography } from '@/theme/index';

const TYPE_LABEL: Record<NotificationType, string> = {
    diary_overdue: 'Diary item overdue',
    diary_due_soon: 'Diary item due soon',
    diary_assigned: 'Diary item assigned to me',
    case_assigned: 'Added to a case',
};
const CHANNELS: { key: NotificationChannelKey; label: string }[] = [
    { key: 'push', label: 'App' },
    { key: 'sms', label: 'SMS' },
    { key: 'email', label: 'Email' },
];
const HHMM = /^([01]\d|2[0-3]):[0-5]\d$/;

/**
 * Which alerts reach this user, and how. Stored on the backend, which also does the sending — so a change here
 * applies on every device. SMS needs a phone number and e-mail an address on the user's profile.
 */
export default function NotificationSettingsScreen() {
    const [prefs, setPrefs] = useState<NotificationPreferences | null>(null);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const load = useCallback(async () => {
        try {
            setPrefs(await fetchNotificationPreferences());
        } catch (e) {
            setError(getApiErrorMessage(e, 'Settings are not available right now.'));
        }
    }, []);

    useEffect(() => {
        // eslint-disable-next-line react-hooks/set-state-in-effect
        load();
    }, [load]);

    if (!prefs) {
        return error ? (
            <View style={styles.container}>
                <Text style={styles.error}>{error} Connect to the internet and try again.</Text>
            </View>
        ) : (
            <LoadingSpinner />
        );
    }

    const toggle = (type: NotificationType, channel: NotificationChannelKey, value: boolean) =>
        setPrefs({ ...prefs, types: { ...prefs.types, [type]: { ...prefs.types[type], [channel]: value } } });

    const setQuiet = (patch: Partial<NotificationPreferences['quietHours']>) =>
        setPrefs({ ...prefs, quietHours: { ...prefs.quietHours, ...patch } });

    const save = async () => {
        if (prefs.quietHours.enabled && (!HHMM.test(prefs.quietHours.start) || !HHMM.test(prefs.quietHours.end))) {
            Alert.alert('Quiet hours', 'Use the 24-hour format HH:MM, for example 21:00.');
            return;
        }
        setSaving(true);
        try {
            setPrefs(await updateNotificationPreferences(prefs));
            Alert.alert('Saved', 'Your notification settings were updated.');
        } catch (e) {
            Alert.alert('Could not save', getApiErrorMessage(e, 'Please try again.'));
        } finally {
            setSaving(false);
        }
    };

    return (
        <ScrollView style={styles.container} contentContainerStyle={styles.content}>
            <Text style={styles.note}>Alerts are sent by the firm&apos;s server. SMS uses the phone number and email uses the address on your profile.</Text>

            {(Object.keys(TYPE_LABEL) as NotificationType[]).map(type => (
                <Card key={type} style={styles.card}>
                    <Text style={styles.cardTitle}>{TYPE_LABEL[type]}</Text>
                    {CHANNELS.map(({ key, label }) => (
                        <View key={key} style={styles.row}>
                            <Text style={styles.rowLabel}>{label}</Text>
                            <Switch value={prefs.types[type][key]} onValueChange={v => toggle(type, key, v)} accessibilityLabel={`${TYPE_LABEL[type]} by ${label}`} />
                        </View>
                    ))}
                </Card>
            ))}

            <Card style={styles.card}>
                <View style={styles.row}>
                    <Text style={styles.cardTitle}>Quiet hours</Text>
                    <Switch value={prefs.quietHours.enabled} onValueChange={v => setQuiet({ enabled: v })} accessibilityLabel="Quiet hours" />
                </View>
                <Text style={styles.note}>No SMS or app alerts in this window (South African time). They still appear in your inbox.</Text>
                {prefs.quietHours.enabled ? (
                    <View style={styles.timeRow}>
                        <View style={styles.timeCol}>
                            <Input label="From (HH:MM)" value={prefs.quietHours.start} onChangeText={v => setQuiet({ start: v })} keyboardType="numbers-and-punctuation" maxLength={5} />
                        </View>
                        <View style={styles.timeCol}>
                            <Input label="Until (HH:MM)" value={prefs.quietHours.end} onChangeText={v => setQuiet({ end: v })} keyboardType="numbers-and-punctuation" maxLength={5} />
                        </View>
                    </View>
                ) : null}
            </Card>

            <Button label="Save settings" onPress={save} loading={saving} />
        </ScrollView>
    );
}

const styles = StyleSheet.create({
    container: { flex: 1, backgroundColor: colors.background },
    content: { padding: spacing.md, paddingBottom: spacing.xl },
    note: { ...typography.caption, color: colors.textSecondary, marginBottom: spacing.md },
    error: { ...typography.body, color: colors.red, padding: spacing.lg },
    card: { marginBottom: spacing.md },
    cardTitle: { ...typography.body, fontWeight: '700', color: colors.textPrimary },
    row: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginTop: spacing.xs },
    rowLabel: { ...typography.body, color: colors.textSecondary },
    timeRow: { flexDirection: 'row', gap: spacing.md },
    timeCol: { flex: 1 },
});
