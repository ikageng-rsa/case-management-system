import { Document, indexIncluded, Resource } from '@/api/jsonapi';
import { mapMatter } from '@/api/mappers/matter';
import { mapDiaryEntry } from '@/api/mappers/diary';
import { mapNarration } from '@/api/mappers/narration';
import { SyncEntity } from './types';

/**
 * Turns the server's answer to a queued request into the app's own record shape.
 * The sync engine uses this for replayed requests; online calls use the same function so both paths agree.
 */
export function parseServerRecord(entity: SyncEntity, url: string, body: unknown): { id: string } & Record<string, any> {
    const doc = body as Document<Resource>;
    const resource = doc?.data as Resource | undefined;
    if (!resource || Array.isArray(resource)) {
        throw new Error('Unexpected response from the server.');
    }
    const included = indexIncluded(doc.included);
    if (entity === 'narration') {
        const caseId = url.match(/\/matters\/([^/]+)\/narrations/)?.[1] ?? '';
        return mapNarration(resource, included, caseId);
    }
    if (entity === 'case') {
        return mapMatter(resource, included);
    }
    if (entity === 'darzation') {
        const caseId = url.match(/\/matters\/([^/]+)\/diary-entries/)?.[1] ?? '';
        return mapDiaryEntry(resource, included, caseId);
    }
    return { id: String(resource.id), ...(resource.attributes ?? {}) };
}
