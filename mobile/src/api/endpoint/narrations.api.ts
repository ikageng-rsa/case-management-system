import { apiClient } from '@/api/client';
import { Narration } from '@/models/index';

export async function fetchNarrationsForCase(caseId: string): Promise<Narration[]> {
  const { data } = await apiClient.get<Narration[]>(`/cases/${caseId}/narrations`);
  return data;
}

export async function addNarration(payload: Omit<Narration, 'id' | 'createdAt'>): Promise<Narration> {
  const { data } = await apiClient.post<Narration>(`/cases/${payload.caseId}/narrations`, payload);
  return data;
}
