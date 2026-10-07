import { apiClient } from '@/api/client';

export interface DashboardSummary {
  openCases: number;
  overdueDarzations: number;
  unbilledEntries: number;
  documentsThisWeek: number;
}

export async function fetchDashboardSummary(): Promise<DashboardSummary> {
  const { data } = await apiClient.get<DashboardSummary>('/reports/dashboard');
  return data;
}
