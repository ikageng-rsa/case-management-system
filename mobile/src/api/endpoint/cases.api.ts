import { apiClient } from '@/api/client';
import { Case } from '@/models/index';
import { CacheNames } from '@/offline/cache';
import { getActiveUserId } from '@/offline/context';
import { cachedList, cachedRecord, submitCreate, submitUpdate } from '@/offline/repository';
import { FeatureUnavailableError } from '@/config/features';
import { fetchAllPage } from '../pagination';
import { mapMatter, mapMatterList } from '../mappers/matter';
import { Document, indexIncluded, Resource } from '../jsonapi';

type CaseFilter = { status?: string; search?: string };

/** The same filter the server applies, for answering a filtered list from the on-device copy. */
function filterCases(rows: Case[], params?: CaseFilter): Case[] {
  const term = params?.search?.trim().toLowerCase() ?? '';
  return rows.filter(
    c =>
      (!params?.status || c.status === params.status) &&
      (!term ||
        [c.fileReference, c.clientName, c.matterType, c.opposingParty, c.courtCaseNumber].some(v =>
          (v ?? '').toLowerCase().includes(term),
        )),
  );
}

//Matters the signed-in user can see (the server decides which). Follows every page
export async function fetchCases(params?: CaseFilter): Promise<Case[]> {
  const unfiltered = !params?.status && !params?.search;
  return cachedList<Case>({
    name: CacheNames.cases,
    entity: 'case',
    cacheable: unfiltered,
    request: async () => {
      const q = params?.search?.trim();
      const {data,included} = await fetchAllPage('/matters', q ? {q}: {});
      return mapMatterList(data, included, getActiveUserId());
    },
    filter: unfiltered ? undefined : rows => filterCases(rows, params),
  });
}

export async function fetchCaseById(id: string): Promise<Case> {
  return cachedRecord<Case>({
    id,
    listName: CacheNames.cases,
    entity: 'case',
    request: async () => {
      const {data:doc} = await apiClient.get<Document<Resource>>(`/matters/${id}`);
      return mapMatter(doc.data as Resource, indexIncluded(doc.included), getActiveUserId());
    },
  });
}
//The v1 API has no create /edit for matters. These stay so the screens compile and fail politely if reached
export async function createCase(_payload: Partial<Case>): Promise<Case>{
  throw new FeatureUnavailableError('Opening a new matter/case');
}

export async function updateCase(_id:string,_payload: Partial<Case>): Promise<Case>{
  throw new FeatureUnavailableError('Editing a matter');

}

export async function updateCaseStatus(_id: string, _status: Case['status']): Promise<Case>{
    throw new FeatureUnavailableError('Changin a matter status');

}
//helder function for the label not be a nested template literals
/*function getNewCaseLabel(payload:{
  clientName?: string | null,
  matterType?: string | null
}){
  const clientName = payload.clientName ?? 'untitled';
  const matterType = payload.matterType ? `(${payload.matterType})` : '';

  return `New case - ${clientName}${matterType}`;
}

export async function createCase(payload: Partial<Case>): Promise<Case> {
  const me = getActiveUserId() ?? '';
  return submitCreate<Case>({
    entity: 'case',
    label: getNewCaseLabel(payload),
    url: '/cases',
    body: payload as Record<string, unknown>,
    optimistic: (id, createdAt) =>
      ({
        status: 'Open',
        priority: 'Medium',
        clientName: '',
        matterType: '',
        openedDate: createdAt.slice(0, 10),
        ...payload,
        id,
        fileReference: 'Awaiting sync',
        clientId: '',
        leadAttorneyId: me,
        // The creator is always on the case (the server does the same), so it stays visible to them.
        assignedUserIds: [...new Set([me, ...(payload.assignedUserIds ?? [])])],
      }) as Case,
    listName: () => CacheNames.cases,
  });
}

export async function updateCase(id: string, payload: Partial<Case>): Promise<Case> {
  return submitUpdate<Case>({
    entity: 'case',
    entityId: id,
    action: 'Editing a case',
    label: 'Edit case',
    url: `/cases/${id}`,
    body: payload as Record<string, unknown>,
    patch: { kind: 'merge', fields: payload as Record<string, unknown> },
    listName: CacheNames.cases,
  });
}

export async function updateCaseStatus(id: string, status: Case['status']): Promise<Case> {
  return submitUpdate<Case>({
    entity: 'case',
    entityId: id,
    action: 'Changing a case status',
    label: `Set case status to ${status}`,
    url: `/cases/${id}/status`,
    body: { status },
    patch: { kind: 'merge', fields: { status } },
    listName: CacheNames.cases,
  });
}
*/