import { apiClient } from '@/api/client';
import { Narration } from '@/models/index';
import {CacheNames} from '@/offline/cache';
import {getActiveUserId} from '@/offline/context';
import {cachedList, cachedRecord, submitCreate} from '@/offline/repository';

export async function fetchNarrationsForCase(caseId: string): Promise<Narration[]> {
  return cachedList<Narration>({
    name: CacheNames.narrations(caseId),
    entity: 'narration',
    inScope: (row) => row.caseId === caseId,
    request: async () => ( await apiClient.get<Narration[]>(`/cases/${caseId}/narrations`)).data
  })
}

export async function fetchNarrationById(caseId: string, narrationId: string): Promise<Narration>{
  return cachedRecord<Narration>({
      id: narrationId,
      listName: CacheNames.narrations(caseId),
      entity: 'narration',
      inScope: (row) => row.caseId === caseId,
      request: async () => ( await apiClient.get<Narration>(`/cases/${caseId}/narrations/${narrationId}`)).data
  });
}

export async function addNarration(payload: Omit<Narration, 'id' | 'createdAt' | 'documentIds'>): Promise<Narration> {
  const me = getActiveUserId() ?? '';
  return submitCreate<Narration>({
    entity: 'narration',
    label: `New narration - ${payload.title || payload.description.slice(0,40)}`,
    url: `/case/${payload.caseId}/narrations`,
    body: {...payload, documentIds: []},
    optimistic: (id, createdAt) => ({ ...payload, id, authorId: me, documentIds: [], createdAt}),
    listName: (created:Narration) => CacheNames.narrations(created.caseId)
  });
}

export async function fetchNarrationsForDarzation(caseId: string, darzationId: string): Promise<Narration[]>{
  const all = await fetchNarrationsForCase(caseId);
  return all.filter(n => n.darzationId === darzationId);
}
