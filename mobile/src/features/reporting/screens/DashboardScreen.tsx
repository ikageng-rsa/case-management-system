import React from 'react';
import { RefreshControl, ScrollView, Text } from 'react-native';
import { useRouter } from 'expo-router';
import LoadingSpinner from '@/components/LoadingSpinner';
import { useAppSelector } from '@/hooks/useAppDispatch';
import { canAccessTab } from '@/constants/roles';
import { useDashboardData } from '@/features/reporting/hooks/useDashboardData';
import { buildTiles } from '@/utils/dashboardTiles';
import DashboardTiles from '@/components/DashboardTiles';
import DiaryAttentionSection from '@/components/DiaryAttentionSection';
import CasesSection from '@/components/CasesSection';
import RecentActivitySection from '@/components/RecentActivitySection';
import { styles } from '@/features/reporting/dashboardStyles';

const SCOPE_TEXT = {
  firm: 'Firm-wide view — all cases, diary items and activity',
  mine: 'Your view — cases you are assigned to or have worked on',
};

export default function DashboardScreen() {
  const router = useRouter();
  const role = useAppSelector(state => state.auth.user?.role);
  const { data, loading, error, reload } = useDashboardData();

  if (loading) {
    return <LoadingSpinner />;
  }

  const showCases = !!role && canAccessTab('Cases', role);
  const showDiary = !!role && canAccessTab('Diary', role);
  const tiles = buildTiles(data.stats, { showCases, showDiary }, router);

  return (
    <ScrollView
      style={styles.container}
      contentContainerStyle={styles.content}
      refreshControl={<RefreshControl refreshing={false} onRefresh={reload} />}
    >
      <Text style={styles.title}>Dashboard</Text>
      <Text style={styles.scope}>{data.scope === 'firm' ? SCOPE_TEXT.firm : SCOPE_TEXT.mine}</Text>
      {error ? <Text style={styles.error}>{error}</Text> : null}

      <DashboardTiles tiles={tiles} />
      {showDiary ? <DiaryAttentionSection items={data.overdue} /> : null}
      {showCases ? <CasesSection cases={data.cases} scope={data.scope} /> : null}
      {showCases ? <RecentActivitySection items={data.narrations} /> : null}
    </ScrollView>
  );
}