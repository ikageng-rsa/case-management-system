import {apiClient} from "../client";
import {Document , indexIncluded, relOne, Resource, includedGet} from '@/api/jsonapi';

export interface AuditChange{
    field: string;
    old: unknown;
    new: unknown;
}

export interface AuditEntry{
    id: string;
    //created, updated, deleted, read, downloaded, uploaded, etc.
    event: string;
    description: string;
    //Matter, narration, diary_entry, matter_Assignment, matter_party, document
    subjectType: string;
    subjectId: string;
    changes: AuditChange[];
    //Which field, where one was read on its own
    field: string | null;
    ip?: string;
    //web or api
    via?: string;
    createdAt: string;  
    //Null when the system did it.
    causerName?: string | null;
}

export interface AuditPage{
    entries: AuditEntry[];
    hasMore: boolean;
}

function mapEntry(resource: Resource, included: ReturnType<typeof indexIncluded>): AuditEntry{
    const a = resource.attributes ?? {};
    const rawChanges = (a.changes ?? {}) as Record<string, {old?: unknown; new?:unknown}>;
    const causer = includedGet(included, relOne(resource, 'causer'));
    return{
        id: String(resource.id),
        event: a.event ?? '',
        description: a.description ?? '',
        subjectType: a.subject_type ?? '',
        subjectId: a.subject_id ? String(a.subject_id) : '',
        changes: Object.entries(rawChanges).map(([field,c]) => ({field, old: c?.old, new: c?.new})),
        field: a.field ?? null,
        ip: a.ip ?? undefined,
        via: a.via ?? undefined,
        createdAt: a.created_at ?? '',
        causerName: causer?.attributes?.name ?? null
    };
}

/**
 * One page (25) of a matter's audit trail, newest first: the matter itself and its narrations, diary entries,
 * assignments, parties and documents. needs the view_audit_log permission (403) and a matter the user can see.
 * Never cached on the phone: it is a record of who did what, read live.
 */

export async function fetchAuditPage(matterId: string, page = 1): Promise<AuditPage>{
    const {data:doc} = await apiClient.get<Document<Resource[]>>(`/matters/${matterId}/audit-entries`,{params: {page}});
    const included = indexIncluded(doc.included);
    const last = Number(doc.meta?.last_page ?? 1);
    return{entries: (doc.data ?? []).map(r => mapEntry(r,included)), hasMore: Boolean(doc.links?.next) && page< last}

}