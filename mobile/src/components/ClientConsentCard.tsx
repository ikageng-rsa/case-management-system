import React from 'react';
import { Alert, Switch, Text, View } from 'react-native';
import Card from '@/components/Card';
import SectionHeader from '@/components/SectionHeader';
import { ClientProfile, PopiaConsentType } from '@/models/index';
import { styles } from '@/features/profile/clientProfileStyles';

const CONSENTS: { type: PopiaConsentType; label: string }[] = [
    { type: 'DataProcessing', label: 'Processing of personal information' },
    { type: 'DocumentRetention', label: 'Document retention' },
    { type: 'ThirdPartySharing', label: 'Sharing with third parties' },
    { type: 'Marketing', label: 'Marketing communication' },
];

interface Props {
    client: ClientProfile;
    canEdit: boolean;
    saving: boolean;
    onRecord: (type: PopiaConsentType, granted: boolean) => Promise<boolean>;
}

type Latest = NonNullable<ClientProfile['consents']>[number] | undefined;

function consentStatus(latest: Latest): string {
    if (!latest) {
        return 'Not recorded';
    }
    return `${latest.granted ? 'Granted' : 'Withdrawn'} on ${latest.recordedAt.slice(0, 10)}`;
}

export default function ClientConsentCard({ client, canEdit, saving, onRecord }: Readonly<Props>) {
    const confirm = (type: PopiaConsentType, label: string, granted: boolean) =>
        Alert.alert(
            granted ? 'Record consent' : 'Record withdrawal',
            `${granted ? 'The client has agreed to' : 'The client has withdrawn consent for'}: ${label.toLowerCase()}.`,
            [
                { text: 'Cancel', style: 'cancel' },
                { text: 'Confirm', onPress: () => void onRecord(type, granted) },
            ],
        );

    return (
        <>
            <SectionHeader title="POPIA consent" />
            <Card style={styles.card}>
                {CONSENTS.map(({ type, label }) => {
                    const latest = (client.consents ?? []).find(c => c.consentType === type);
                    return (
                        <View key={type} style={styles.consentRow}>
                            <View style={styles.flex}>
                                <Text style={styles.consentLabel}>{label}</Text>
                                <Text style={styles.muted}>{consentStatus(latest)}</Text>
                            </View>
                            {canEdit ? (
                                <Switch
                                    accessibilityLabel={label}
                                    value={Boolean(latest?.granted)}
                                    disabled={saving}
                                    onValueChange={v => confirm(type, label, v)}
                                />
                            ) : null}
                        </View>
                    );
                })}
            </Card>
        </>
    );
}