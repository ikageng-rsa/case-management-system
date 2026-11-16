import { Resource } from '@/api/jsonapi';
import { CaseDocument, DocumentKind } from '@/models/index';

export function mapDocument(resource: Resource, caseId: string): CaseDocument {
    const a = resource.attributes ?? {};
    const fileName = a.file_name ?? '';
    return {
        id: String(resource.id),
        caseId,
        name: a.name || fileName,
        fileName,
        kind: a.kind ?? '',
        uploadedAt: a.created_at ?? '',
        mimeType: a.mime_type ?? undefined,
        sizeBytes: typeof a.size === 'number' ? a.size : undefined,
        syncStatus: 'synced',
    };
}

export function mapDocumentKind(resource: Resource): DocumentKind {
    return { id: String(resource.id), name: resource.attributes?.name ?? String(resource.id) };
}

/** 182044 → "178 KB". */
export function formatFileSize(bytes?: number): string {
    if (bytes === undefined) {
        return '';
    }
    if (bytes < 1024) {
        return `${bytes} B`;
    }
    if (bytes < 1024 * 1024) {
        return `${Math.round(bytes / 1024)} KB`;
    }
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}
