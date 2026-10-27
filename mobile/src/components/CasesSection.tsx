import React from 'react';
import { Pressable, Text } from 'react-native';
import { useRouter } from 'expo-router';
import Card from '@/components/Card';
import EmptyState from '@/components/EmptyState';
import PriorityBadge from '@/components/PriorityBadge';
import SectionHeader from '@/components/SectionHeader';
import { Case } from '@/models/index';
import { styles } from '@/features/reporting/dashboardStyles';
import { LIST_LIMIT } from './DiaryAttentionSection';

export default function CasesSection({ cases, scope }: { cases: Case[]; scope: string }) {
    const router = useRouter();
    return (
        <>
            <SectionHeader title={scope === 'firm' ? 'Firm cases' : 'My cases'} eyebrow="CASES" />
            {cases.length === 0 ? <EmptyState title="No cases yet" /> : null}
            {cases.slice(0, LIST_LIMIT).map(c => (
                <Pressable key={c.id} onPress={() => router.push({ pathname: '/cases/[caseId]', params: { caseId: c.id } })}>
                    <Card>
                        <Text style={styles.itemMeta}>{c.fileReference}</Text>
                        <Text style={styles.itemTitle}>{c.clientName}</Text>
                        {c.priority ? <PriorityBadge priority={c.priority} /> : null}
                    </Card>
                </Pressable>
            ))}
        </>
    );
}