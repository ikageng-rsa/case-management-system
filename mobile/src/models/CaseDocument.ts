export type DocumentConfidentiality = 'Standard' | 'Restricted' | 'Privileged';

export interface CaseDocument {
  id: string;
  caseId: string;
  fileName: string;
  version: number;
  confidentiality: DocumentConfidentiality;
  uploadedById: string;
  uploadedAt: string;
  uri: string;
  cachedOffline?: boolean;
}
