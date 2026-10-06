import { apiClient } from '@/api/client';
import { Darzation } from '@/models/index';

export async function fetchDiary(ownerId?: string): Promise<Darzation[]> {
  const { data } = await apiClient.get<Darzation[]>('/darzations', { params: { ownerId } });
  return data;
}

export async function fetchOverdueDarzations(): Promise<Darzation[]> {
  const { data } = await apiClient.get<Darzation[]>('/darzations/overdue');
  return data;
}

export async function createDarzation(payload: Omit<Darzation, 'id' | 'overdue'>): Promise<Darzation> {
  const { data } = await apiClient.post<Darzation>('/darzations', payload);
  return data;
}
