import React from 'react';
import { Pressable, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useRouter } from 'expo-router';
import Card from '@/components/Card';
import EmptyState from '@/components/EmptyState';
import LoadingSpinner from '@/components/LoadingSpinner';
import PriorityBadge from '@/components/PriorityBadge';
import SectionHeader from '@/components/SectionHeader';
import { useAppSelector } from '@/hooks/useAppDispatch';
import { canAccessTab } from '@/constants/roles';
import { useDashboardData } from '@/features/reporting/hooks/useDashboardData';
import { formatDuration } from '@/utils/formatDuration';
import { colors, spacing, typography } from '@/theme/index';

const LIST_LIMIT = 5;

export default function DashboardScreen() {
  const router = useRouter();
  const user = useAppSelector(state => state.auth.user);
  const { data, loading, error, reload } = useDashboardData();

  if (loading) {
    return <LoadingSpinner />;
  }

  const role = user?.role;
  const showCases = role ? canAccessTab('Cases', role) : false;
  const showDiary = role ? canAccessTab('Diary', role) : false;
  const { stats } = data;

  const tiles = [
    { label: 'Open cases', value: stats.openCases, color: colors.navy, onPress: showCases ? () => router.navigate('/cases') : undefined },
    { label: 'Urgent cases', value: stats.urgentCases, color: colors.burgundy, onPress: showCases ? () => router.navigate('/cases') : undefined },
    { label: 'Overdue diary', value: stats.overdueDiary, color: colors.red, onPress: showDiary ? () => router.push('/diary/overdue') : undefined },
    { label: 'Due this week', value: stats.dueThisWeek, color: colors.gold, onPress: showDiary ? () => router.navigate('/diary') : undefined },
    { label: 'Entries this week', value: stats.narrationsThisWeek, color: colors.green },
    { label: 'Time this week', value: formatDuration(stats.minutesThisWeek), color: colors.purple },
  ];

  return (
    <ScrollView
      style={styles.container}
      contentContainerStyle={styles.content}
      refreshControl={<RefreshControl refreshing={false} onRefresh={reload} />}
    >
      <Text style={styles.title}>Dashboard</Text>
      <Text style={styles.scope}>
        {data.scope === 'firm' ? 'Firm-wide view — all cases, diary items and activity' : 'Your view — cases you are assigned to or have worked on'}
      </Text>
      {error ? <Text style={styles.error}>{error}</Text> : null}

      <View style={styles.grid}>
        {tiles.map(tile => (
          <Pressable key={tile.label} style={styles.tileWrap} disabled={!tile.onPress} onPress={tile.onPress}>
            <Card style={styles.tile}>
              <Text style={[styles.tileValue, { color: tile.color }]}>{tile.value}</Text>
              <Text style={styles.tileLabel}>{tile.label}</Text>
            </Card>
          </Pressable>
        ))}
      </View>

      {showDiary ? (
        <>
          <SectionHeader title="Needs attention" eyebrow="DIARY" />
          {data.overdue.length === 0 ? <EmptyState title="Nothing overdue" /> : null}
          {data.overdue.slice(0, LIST_LIMIT).map(d => (
            <Pressable key={d.id} onPress={() => router.push({ pathname: '/diary/[darzationId]', params: { darzationId: d.id } })}>
              <Card style={styles.overdueCard}>
                <Text style={styles.itemTitle}>{d.title || d.nextAction}</Text>
                <Text style={styles.overdueText}>Was due {d.dueDate || d.nextActionDate}</Text>
              </Card>
            </Pressable>
          ))}
        </>
      ) : null}

      {showCases ? (
        <>
          <SectionHeader title={data.scope === 'firm' ? 'Firm cases' : 'My cases'} eyebrow="CASES" />
          {data.cases.length === 0 ? <EmptyState title="No cases yet" /> : null}
          {data.cases.slice(0, LIST_LIMIT).map(c => (
            <Pressable key={c.id} onPress={() => router.push({ pathname: '/cases/[caseId]', params: { caseId: c.id } })}>
              <Card>
                <Text style={styles.itemMeta}>{c.fileReference}</Text>
                <Text style={styles.itemTitle}>{c.clientName}</Text>
                {c.priority ? <PriorityBadge priority={c.priority} /> : null}
              </Card>
            </Pressable>
          ))}
        </>
      ) : null}

      {showCases ? (
        <>
          <SectionHeader title="Recent activity" eyebrow="NARRATIONS" />
          {data.narrations.length === 0 ? <EmptyState title="No activity logged yet" /> : null}
          {data.narrations.slice(0, LIST_LIMIT).map(n => (
            <Pressable
              key={n.id}
              onPress={() =>
                router.push({ pathname: '/cases/[caseId]/narrations/[narrationId]', params: { caseId: n.caseId, narrationId: n.id } })
              }
            >
              <Card>
                <Text style={styles.itemTitle}>{n.title || n.activityType}</Text>
                <Text style={styles.itemMeta} numberOfLines={2}>
                  {formatDuration(n.timeSpentMinutes ?? 0)} · {n.description}
                </Text>
              </Card>
            </Pressable>
          ))}
        </>
      ) : null}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.background },
  content: { padding: spacing.lg, paddingBottom: spacing.xl * 2 },
  title: { ...typography.h1, color: colors.navy },
  scope: { ...typography.caption, color: colors.textSecondary, marginBottom: spacing.md },
  error: { ...typography.body, color: colors.danger, marginBottom: spacing.md },
  grid: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.md, marginBottom: spacing.lg },
  tileWrap: { width: '47%' },
  tile: { marginBottom: 0 },
  tileValue: { ...typography.h1, fontSize: 28 },
  tileLabel: { ...typography.body, color: colors.textSecondary, marginTop: spacing.xs },
  overdueCard: { borderColor: colors.red },
  overdueText: { ...typography.caption, color: colors.red, marginTop: spacing.xs },
  itemTitle: { ...typography.body, fontWeight: '600', color: colors.textPrimary },
  itemMeta: { ...typography.caption, color: colors.textSecondary, marginVertical: 2 },
});
