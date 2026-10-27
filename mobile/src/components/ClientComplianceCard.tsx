import React from 'react';
import { Switch, Text, View } from 'react-native';
import Card from '@/components/Card';
import ChipSelect from '@/components/ChipSelect';
import SectionHeader from '@/components/SectionHeader';
import { ClientChanges } from '@/api/endpoint/clients.api';
import { ClientProfile, RiskRating } from '@/models/index';
import { styles } from '@/features/profile/clientProfileStyles';

const RISK: RiskRating[] = ['Low', 'Medium', 'High'];

interface Props {
    client: ClientProfile;
    canCompliance: boolean;
    offline: boolean;
    saving: boolean;
    onSave: (changes: ClientChanges) => Promise<boolean>;
}

function ficaSummary(client: ClientProfile): string {
    if (!client.ficaVerified) {
        return 'Identity documents not yet verified';
    }
    const when = client.ficaVerifiedAt ? ` on ${client.ficaVerifiedAt.slice(0, 10)}` : '';
    return `Verified${when}`;
}

export default function ClientComplianceCard({ client, canCompliance, offline, saving, onSave }: Props) {
    const changeRisk = ([risk]: RiskRating[]) => {
        const allowed = Boolean(risk) && canCompliance && risk !== client.riskRating;
        if (allowed) {
            void onSave({ riskRating: risk });
        }
    };

    return (
        <>
            <SectionHeader title="FICA & risk" />
            <Card style={styles.card}>
                <View style={styles.row}>
                    <View style={styles.flex}>
                        <Text style={styles.cardTitle}>FICA verified</Text>
                        <Text style={styles.muted}>{ficaSummary(client)}</Text>
                    </View>
                    <Switch
                        accessibilityLabel="FICA verified"
                        value={client.ficaVerified}
                        disabled={!canCompliance || saving}
                        onValueChange={v => void onSave({ ficaVerified: v })}
                    />
                </View>
                <ChipSelect
                    label="Risk rating"
                    options={RISK.map(r => ({ value: r, label: r }))}
                    selected={[client.riskRating]}
                    onChange={changeRisk}
                />
                {!canCompliance && !offline ? <Text style={styles.muted}>Only a CA, Director or Admin can change these.</Text> : null}
            </Card>
        </>
    );
}