import React from 'react';
import { Pressable, Text } from 'react-native';
import { useRouter } from 'expo-router';
import Button from '@/components/Button';
import Card from '@/components/Card';
import SectionHeader from '@/components/SectionHeader';
import { Case } from '@/models/index';
import { styles } from '@/features/profile/clientProfileStyles';

interface Props {
    cases: Case[];
    clientName: string;
    canCreate: boolean;
}

export default function ClientCasesSection({ cases, clientName, canCreate }: Props) {
    const router = useRouter();
    return (
        <>
            <SectionHeader title={`Cases (${cases.length})`} />
            {cases.length === 0 ? <Text style={styles.muted}>No cases you can see for this client.</Text> : null}
            {cases.map(c => (
                <Pressable key={c.id} onPress={() => router.push({ pathname: '/cases/[caseId]', params: { caseId: c.id } })}>
                    <Card>
                        <Text style={styles.consentLabel}>{c.fileReference}</Text>
                        <Text style={styles.body}>
                            {c.matterType} · {c.status}
                        </Text>
                    </Card>
                </Pressable>
            ))}
            {canCreate ? (
                <Button
                    label="+ New case for this client"
                    onPress={() => router.push({ pathname: '/cases/new', params: { clientName } })}
                    style={styles.gap}
                />
            ) : null}
        </>
    );
}