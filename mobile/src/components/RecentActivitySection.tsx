import React from 'react';
import { Pressable, Text } from 'react-native';
import { useRouter } from 'expo-router';
import Card from '@/components/Card';
import EmptyState from '@/components/EmptyState';
import SectionHeader from '@/components/SectionHeader';
import { formatDuration } from '@/utils/formatDuration';
import { Narration } from '@/models/index';
import { styles } from '@/features/reporting/dashboardStyles';
import { LIST_LIMIT } from './DiaryAttentionSection';

export default function RecentActivitySection({ items }: Readonly<{ items: Narration[] }>) {
  const router = useRouter();
  const open = (n: Narration) =>
    router.push({ pathname: '/cases/[caseId]/narrations/[narrationId]', params: { caseId: n.caseId, narrationId: n.id } });

  return (
    <>
      <SectionHeader title="Recent activity" eyebrow="NARRATIONS" />
      {items.length === 0 ? <EmptyState title="No activity logged yet" /> : null}
      {items.slice(0, LIST_LIMIT).map(n => (
        <Pressable key={n.id} onPress={() => open(n)}>
          <Card>
            <Text style={styles.itemTitle}>{n.title || n.activityType}</Text>
            <Text style={styles.itemMeta} numberOfLines={2}>
              {formatDuration(n.timeSpentMinutes ?? 0)} · {n.description}
            </Text>
          </Card>
        </Pressable>
      ))}
    </>
  );
}