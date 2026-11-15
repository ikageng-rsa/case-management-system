import { apiClient } from '@/api/client';

export interface DashboardSummary {
  openCases: number;
  overdueDarzations: number;
  documentsThisWeek: number;
  myCases?: number;
  myOverdueDarzations?: number;
}

export async function fetchDashboardSummary(): Promise<DashboardSummary> {
  const { data } = await apiClient.get<DashboardSummary>('/reports/dashboard');
  return data;
}
