import { apiClient } from '@/api/client';
import { Darzation } from '@/models/index';
import {computeOverdue} from '@/models/Darzation';
import {CacheNames} from '@/offline/cache';
import {getActiveUserId} from '@/offline/context';
import {cachedList, cachedRecord, submitCreate,submitUpdate} from '@/offline/repository';

//Server semantics: overdue = not done and due before today. Recomputed so cached rows don't go stale

const withLiveOverdue = (rows: Darzation[]): Darzation[] =>{
  const today = new Date().toISOString().slice(0,10);
  return rows.map(d => ({...d, overdue: (d.status ?? 'Pending') !== 'Done' && (d.dueDate || d.nextActionDate).slice(0,10) < today}));
};


export async function fetchDiary(ownerId?: string): Promise<Darzation[]> {
  return cachedList<Darzation>({
    name: CacheNames.diary,
    entity: 'darzation',
    cacheable: !ownerId,
    request: async () => (await apiClient.get<Darzation[]>('/darzations', {params: {ownerId}})).data,
    filter: (rows: Darzation[]) => withLiveOverdue(ownerId ? rows.filter(d => d.ownerId === ownerId) : rows)
  })
}

export async function fetchDarzationById(id: string): Promise<Darzation>{
    return cachedRecord<Darzation>({
      id,
      listName: CacheNames.diary,
      entity: 'darzation',
      request: async () => (await apiClient.get<Darzation>(`/darzations/${id}`)).data
    })
}

export async function fetchOverdueDarzations(): Promise<Darzation[]> {
  return cachedList<Darzation>({
    name: CacheNames.diary,
    entity: 'darzation',
    cacheable: false,
    request: async () => (await apiClient.get<Darzation[]>('/darzations/overdue')).data,
    //Offline the list is worked out on the devices from the cached diary
    filter: (rows:Darzation[]) => withLiveOverdue(rows).filter(d => d.overdue)
  })
}

export async function createDarzation(payload: Omit<Darzation, 'id' | 'overdue'>): Promise<Darzation> {
  const me = getActiveUserId() ?? '';
  return submitCreate<Darzation>({
    entity: 'darzation',
    label: `New diary item - ${payload.title}`,
    url: '/diary',
    body: payload as Record<string, unknown>,
    optimistic: id =>({
      ...payload,
      id,
      ownerId: me,
      assignedUserIds: [...new Set([me, ...(payload.assignedUserIds ?? [])])],
      overdue: computeOverdue(payload.dueDate, payload.status ?? 'Pending'),
    }),
    listName: () => CacheNames.diary,
  })
}


export async function updateDarzation(id: string, payload: Partial<Darzation>): Promise<Darzation> {
  return submitUpdate<Darzation>({
    entity: 'darzation',
    entityId: id,
    action: 'Editing a diary item',
    label: 'Edit diary item',
    url: `/diary/${id}`,
    body: payload as Record<string, unknown>,
    patch: { kind: 'merge', fields: payload as Record<string, unknown> },
    listName: CacheNames.diary,
  });
}

export async function toggleDarzationStep(darzationId: string, stepId: string, done: boolean): Promise<Darzation> {
  return submitUpdate<Darzation>({
    entity: 'darzation',
    entityId: darzationId,
    action: 'Ticking a diary step',
    label: `${done ? 'Complete' : 'Reopen'} a diary step`,
    url: `/diary/${darzationId}/steps/${stepId}`,
    body: { done },
    patch: { kind: 'step', stepId, done },
    listName: CacheNames.diary,
  });
}

