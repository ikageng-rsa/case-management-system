import { apiClient } from '@/api/client';
import { Case } from '@/models/index';

export async function fetchCases(params?: { status?: string; search?: string }): Promise<Case[]> {
  const { data } = await apiClient.get<Case[]>('/cases', { params });
  return data;
}

export async function fetchCaseById(id: string): Promise<Case> {
  const { data } = await apiClient.get<Case>(`/cases/${id}`);
  return data;
}

export async function createCase(payload: Partial<Case>): Promise<Case> {
  const { data } = await apiClient.post<Case>('/cases', payload);
  return data;
}

export async function updateCaseStatus(id: string, status: Case['status']): Promise<Case> {
  const { data } = await apiClient.patch<Case>(`/cases/${id}/status`, { status });
  return data;
}
