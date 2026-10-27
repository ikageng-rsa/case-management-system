import { useCallback, useEffect, useState } from 'react';
import { fetchCases } from '@/api/endpoint/cases.api';
import { fetchDiary } from '@/api/endpoint/darzations.api';
import { fetchNarrationsForCase } from '@/api/endpoint/narrations.api';
import { useAppSelector } from '@/hooks/useAppDispatch';
import { useOnSynced } from '@/hooks/useSyncStatus';
import { hasGlobalVisibility } from '@/constants/roles';
import { visibleCasesFor, visibleDarzationsFor, visibleNarrationsFor } from '@/utils/caseAccess';
import { computeOverdue } from '@/models/Darzation';
import { Case, Darzation, Narration } from '@/models/index';

const MAX_CASES_FOR_NARRATIONS = 20; // keeps the dashboard to a bounded number of requests

export interface DashboardStats {
  openCases: number;
  urgentCases: number;
  overdueDiary: number;
  dueThisWeek: number;
  narrationsThisWeek: number;
  minutesThisWeek: number;
}

export interface DashboardData {
  scope: 'firm' | 'mine';
  cases: Case[];
  diary: Darzation[];
  overdue: Darzation[];
  narrations: Narration[];
  stats: DashboardStats;
}

const EMPTY: DashboardData = {
  scope: 'mine',
  cases: [],
  diary: [],
  overdue: [],
  narrations: [],
  stats: { openCases: 0, urgentCases: 0, overdueDiary: 0, dueThisWeek: 0, narrationsThisWeek: 0, minutesThisWeek: 0 },
};

const WEEK_MS = 7 * 24 * 60 * 60 * 1000;

/** Pure so it can be unit-tested without hooks/network. */
export function buildDashboardData(
  role: 'firm' | 'mine',
  cases: Case[],
  diary: Darzation[],
  narrations: Narration[],
  now: Date = new Date(),
): DashboardData {
  const overdue = diary.filter(d => computeOverdue(d.dueDate || d.nextActionDate, d.status ?? 'Pending', now));
  const weekAhead = now.getTime() + WEEK_MS;
  const dueThisWeek = diary.filter(d => {
    if (d.status === 'Done') return false;
    const due = new Date(d.dueDate || d.nextActionDate).getTime();
    return due >= now.getTime() && due <= weekAhead;
  });
  const recent = narrations.filter(n => now.getTime() - new Date(n.createdAt).getTime() <= WEEK_MS);

  return {
    scope: role,
    cases,
    diary,
    overdue,
    narrations,
    stats: {
      openCases: cases.filter(c => c.status === 'Open' || c.status === 'In Progress').length,
      urgentCases: cases.filter(c => c.priority === 'Urgent' && c.status !== 'Closed' && c.status !== 'Archived').length,
      overdueDiary: overdue.length,
      dueThisWeek: dueThisWeek.length,
      narrationsThisWeek: recent.length,
      minutesThisWeek: recent.reduce((sum, n) => sum + (n.timeSpentMinutes ?? 0), 0),
    },
  };
}

export function useDashboardData() {
  const user = useAppSelector(state => state.auth.user);
  const [data, setData] = useState<DashboardData>(EMPTY);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    if (!user) {
      return;
    }
    setError(null);
    try {
      const [allCases, allDiary] = await Promise.all([fetchCases(), fetchDiary().catch(() => [] as Darzation[])]);
      const cases = visibleCasesFor(allCases, user);
      const diary = visibleDarzationsFor(allDiary, allCases, user);

      const settled = await Promise.allSettled(
        cases.slice(0, MAX_CASES_FOR_NARRATIONS).map(c => fetchNarrationsForCase(c.id)),
      );
      const allNarrations = settled.flatMap(r => (r.status === 'fulfilled' ? r.value : []));
      const narrations = visibleNarrationsFor(allNarrations, allCases, user).sort((a, b) =>
        b.createdAt.localeCompare(a.createdAt),
      );

      setData(buildDashboardData(hasGlobalVisibility(user.role) ? 'firm' : 'mine', cases, diary, narrations));
    } catch {
      setError('Could not load the dashboard. Pull down to retry.');
    } finally {
      setLoading(false);
    }
  }, [user]);

  // Fetch-on-mount: setState runs after awaited network I/O, not synchronously.
  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect
    load();
  }, [load]);

  // Offline work that has just synced changes ids and totals.
  useOnSynced(load);

  return { data, loading, error, reload: load };
}
