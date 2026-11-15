import * as FileSystem from 'expo-file-system/legacy';
import { apiClient, getAuthToken } from '@/api/client';
import { Document, Resource } from '@/api/jsonapi';
import { mapDocument, mapDocumentKind } from '@/api/mappers/document';
import { fetchAllPage } from '@/api/pagination';
import { CaseDocument, DocumentKind } from '@/models/index';
import { CacheNames } from '@/offline/cache';
import { isDeviceOffline } from '@/offline/connectivity';
import { OfflineNotAllowedError } from '@/offline/policy';
import { cachedList } from '@/offline/repository';

/** The server refuses anything over 50 MB; checking first saves a long upload that would fail. */
export const MAX_DOCUMENT_BYTES = 50 * 1024 * 1024;

export interface UploadFile {
  uri: string;
  name: string;
  type: string;
}

/** Every document on a matter, newest first. Filtering by kind is done on the phone so it also works offline. */
export async function fetchDocumentsForCase(caseId: string, kind?: string): Promise<CaseDocument[]> {
  return cachedList<CaseDocument>({
    name: CacheNames.documents(caseId),
    request: async () => {
      const pages = await fetchAllPage(`/matters/${caseId}/documents`);
      return pages.data.map(r => mapDocument(r, caseId));
    },
    filter: rows => (kind ? rows.filter(d => d.kind === kind) : rows),
  });
}

/** The kinds a document can be filed under (mandate, evidence, court order…). Cached for offline use. */
export async function fetchDocumentKinds(): Promise<DocumentKind[]> {
  return cachedList<DocumentKind>({
    name: CacheNames.documentKinds,
    request: async () => {
      const { data: doc } = await apiClient.get<Document<Resource[]>>('/document-kinds');
      return (doc.data ?? []).map(mapDocumentKind);
    },
  });
}

/** Uploads a file to a matter (multipart). Online only; the server reads the real file type from the contents. */
export async function uploadDocument(caseId: string, file: UploadFile, kind: string, name?: string): Promise<CaseDocument> {
  if (await isDeviceOffline()) {
    throw new OfflineNotAllowedError('Uploading a document');
  }
  const form = new FormData();
  form.append('file', { uri: file.uri, name: file.name, type: file.type } as unknown as Blob);
  form.append('kind', kind);
  if (name?.trim()) {
    form.append('name', name.trim());
  }
  const { data: doc } = await apiClient.post<Document<Resource>>(`/matters/${caseId}/documents`, form, {
    headers: { 'Content-Type': 'multipart/form-data' },
  });
  return mapDocument(doc.data as Resource, caseId);
}

/**
 * Downloads a document into the app's cache folder and returns its path. The file is fetched with the bearer token
 * (there are no public links) and the server writes every download to the audit log. Callers delete the copy
 * after handing it on, see `discardDownload`.
 */
export async function downloadDocument(caseId: string, doc: CaseDocument): Promise<string> {
  if (await isDeviceOffline()) {
    throw new OfflineNotAllowedError('Downloading a document');
  }
  const token = await getAuthToken();
  const safeName = doc.fileName.replace(/[^\w.-]+/g, '_') || `document-${doc.id}`;
  const target = `${FileSystem.cacheDirectory}${doc.id}-${safeName}`;
  const result = await FileSystem.downloadAsync(`${apiClient.defaults.baseURL}/matters/${caseId}/documents/${doc.id}`, target, {
    headers: { Authorization: `Bearer ${token ?? ''}`, Accept: '*/*' },
  });
  if (result.status !== 200) {
    await FileSystem.deleteAsync(target, { idempotent: true }).catch(() => {});
    throw Object.assign(new Error('Download failed'), { response: { status: result.status } });
  }
  return result.uri;
}

export async function discardDownload(uri: string): Promise<void> {
  await FileSystem.deleteAsync(uri, { idempotent: true }).catch(() => {});
}
