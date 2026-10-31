import { apiClient } from '@/api/client';
import { ActivityType, Narration } from '@/models/index';
import { CacheNames } from '@/offline/cache';
import { getActiveUserId } from '@/offline/context';
import { cachedList, cachedRecord, submitCreate } from '@/offline/repository';
import { Document, Resource } from '../jsonapi';
import { fetchAllPage } from '../pagination';
import { FeatureUnavailableError } from '@/config/features';
import { mapActivityType, mapNarrationList } from '../mappers/narration';


export async function fetchNarrationsForCase(caseId: string): Promise<Narration[]> {
  return cachedList<Narration>({
    name: CacheNames.narrations(caseId),
    entity: 'narration',
    inScope: (row) => row.caseId === caseId,
    request: async () => {
      const { data, included } = await fetchAllPage(`/matters/${caseId}/narrations`);
      return mapNarrationList(data, included, caseId);
    }
  })
}

//v1 has no single-narration route , so the entry is found in the matter's list
export async function fetchNarrationById(caseId: string, narrationId: string): Promise<Narration> {
  return cachedRecord<Narration>({
    id: narrationId,
    listName: CacheNames.narrations(caseId),
    entity: 'narration',
    inScope: (row) => row.caseId === caseId,
    request: async () => {
      const { data, included } = await fetchAllPage(`/matters/${caseId}/narrations`);
      const found = mapNarrationList(data, included, caseId).find(n => n.id === narrationId);
      if (!found) {
        throw new Error('This entry could not be found');

      }
      return found;
    }
  });
}
//Activity types (CONS,CORR,TRAVEL...) -what a narration is measured in. Cache for offline use.
export async function fetchActivityType(): Promise<ActivityType[]> {
  return cachedList<ActivityType>({
    name: CacheNames.activityTypes,
    request: async () => {
      const { data: doc } = await apiClient.get<Document<Resource[]>>('/activity-types');
      return (doc.data ?? []).map(mapActivityType);
    }
  })
}

/*
export async function addNarration(payload: Omit<Narration, 'id' | 'createdAt' | 'documentIds'>): Promise<Narration> {
  const me = getActiveUserId() ?? '';
  return submitCreate<Narration>({
    entity: 'narration',
    label: `New narration - ${payload.title || payload.description.slice(0, 40)}`,
    url: `/case/${payload.caseId}/narrations`,
    body: { ...payload, documentIds: [] },
    optimistic: (id, createdAt) => ({ ...payload, id, authorId: me, documentIds: [], createdAt }),
    listName: (created: Narration) => CacheNames.narrations(created.caseId)
  });
}

export async function fetchNarrationsForDarzation(caseId: string, darzationId: string): Promise<Narration[]> {
  const all = await fetchNarrationsForCase(caseId);
  return all.filter(n => n.darzationId === darzationId);
}
*/

export interface NewNarration {
  caseId: string;
  activity: ActivityType;
  /** What was done. */
  description: string;
  /** Amount in the activity's unit. */
  quantity: number;
  /** When the work happened (ISO). */
  occurredAt: string;
}


/** Records a narration on a matter. Works offline: it is queued and sent when the phone is back online. */
export async function addNarration(input: NewNarration): Promise<Narration> {
  const me = getActiveUserId() ?? '';
  const { activity } = input;
  return submitCreate<Narration>({
    entity: 'narration',
    label: `New narration — ${activity.name}: ${input.description.slice(0, 40)}`,
    url: `/matters/${input.caseId}/narrations`,
    body: {
      activity_type_id: Number.isNaN(Number(activity.id)) ? activity.id : Number(activity.id),
      body: input.description,
      quantity: input.quantity,
      occurred_at: input.occurredAt,
    },
    optimistic: (id, createdAt) => ({
      id,
      caseId: input.caseId,
      authorId: me,
      activityType: activity.code,
      activityTypeId: activity.id,
      activityName: activity.name,
      measure: activity.measure,
      description: input.description,
      quantity: input.quantity,
      billableUnits: input.quantity,
      timeSpentMinutes: activity.measure === 'minutes' ? input.quantity : 0,
      billable: true,
      documentIds: [],
      occurredAt: input.occurredAt,
      createdAt,
    }),
    listName: created => CacheNames.narrations(created.caseId),
  });
}


/** v1 has no edit for narrations. */
export async function updateNarration(_caseId: string, _narrationId: string, _changes: Record<string, unknown>): Promise<Narration>{
  throw new FeatureUnavailableError('Editing a narration');                                                                   
}


/** A derived diary item is a matter deadline, so the work shown with it is that matter's narrations. */
export async function fetchNarrationsForDarzation(caseId: string, _darzationId: string): Promise<Narration[]> {
  return fetchNarrationsForCase(caseId);
}

