import React from 'react';
import { Pressable, Text } from 'react-native';
import { useRouter } from 'expo-router';
import Card from '@/components/Card';
import EmptyState from '@/components/EmptyState';
import SectionHeader from '@/components/SectionHeader';
import { Darzation } from '@/models/index';
import { styles } from '@/features/reporting/dashboardStyles';

export const LIST_LIMIT = 5;

export default function DiaryAttentionSection({ items }: Readonly<{ items: Darzation[] }>) {
    const router = useRouter();
    return (
        <>
            <SectionHeader title="Needs attention" eyebrow="DIARY" />
            {items.length === 0 ? <EmptyState title="Nothing overdue" /> : null}
            {items.slice(0, LIST_LIMIT).map(d => (
                <Pressable key={d.id} onPress={() => router.push({ pathname: '/diary/[darzationId]', params: { darzationId: d.id } })}>
                    <Card style={styles.overdueCard}>
                        <Text style={styles.itemTitle}>{d.title || d.nextAction}</Text>
                        <Text style={styles.overdueText}>Was due {d.dueDate || d.nextActionDate}</Text>
                    </Card>
                </Pressable>
            ))}
        </>
    );
}