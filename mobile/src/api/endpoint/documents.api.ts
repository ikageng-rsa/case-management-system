import { apiClient } from '@/api/client';
import { CaseDocument } from '@/models/index';
import {CacheNames} from '@/offline/cache';
import {isDeviceOffline} from '@/offline/connectivity';
import {OfflineNotAllowedError} from '@/offline/policy';
import {cachedList} from '@/offline/repository';

export async function fetchDocumentsForCase(caseId: string): Promise<CaseDocument[]> {
  return cachedList<CaseDocument>({
    name: CacheNames.documents(caseId),
    request: async () => (await apiClient.get<CaseDocument[]>(`/cases/${caseId}/documents`)).data,
  });
}

export async function uploadDocument(
  caseId: string,
  file: { uri: string; name: string; type: string },
  confidentiality: CaseDocument['confidentiality'],
  narrationId?: string
): Promise<CaseDocument> {
  if(await isDeviceOffline()){
    throw new OfflineNotAllowedError('Uploading a document');
  }
  const form = new FormData();
  form.append('file', { uri: file.uri, name: file.name, type: file.type } as any);
  form.append('confidentiality', confidentiality);
  if(narrationId){
    //Backend route for narration-scoped documents may not exist yet - sent as an
    //extra field so the case-level endpoint still accepts the upload, the
    //narration link itself is kept authoritatively on-device (see
    //@feature/documents/services/narrationDocuments.ts) until the backend adds
    //a match column/endpoint.
    form.append('narrationId', narrationId);
  }

  const { data } = await apiClient.post<CaseDocument>(`/cases/${caseId}/documents`, form, {
    headers: { 'Content-Type': 'multipart/form-data' },
  });
  return data;
}
