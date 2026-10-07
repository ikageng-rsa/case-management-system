import { apiClient } from '@/api/client';
import { CaseDocument } from '@/models/index';

export async function fetchDocumentsForCase(caseId: string): Promise<CaseDocument[]> {
  const { data } = await apiClient.get<CaseDocument[]>(`/cases/${caseId}/documents`);
  return data;
}

export async function uploadDocument(
  caseId: string,
  file: { uri: string; name: string; type: string },
  confidentiality: CaseDocument['confidentiality'],
): Promise<CaseDocument> {
  const form = new FormData();
  form.append('file', { uri: file.uri, name: file.name, type: file.type } as any);
  form.append('confidentiality', confidentiality);

  const { data } = await apiClient.post<CaseDocument>(`/cases/${caseId}/documents`, form, {
    headers: { 'Content-Type': 'multipart/form-data' },
  });
  return data;
}
