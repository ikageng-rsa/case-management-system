import { apiClient } from '@/api/client';
import { BillingEntry } from '@/models/index';

export async function fetchBillingForCase(caseId: string): Promise<BillingEntry[]> {
  const { data } = await apiClient.get<BillingEntry[]>(`/cases/${caseId}/billing`);
  return data;
}

export async function markBilled(entryId: string): Promise<BillingEntry> {
  const { data } = await apiClient.patch<BillingEntry>(`/billing/${entryId}/mark-billed`);
  return data;
}
