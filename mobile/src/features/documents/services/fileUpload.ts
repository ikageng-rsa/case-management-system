import { uploadDocument } from '@/api/endpoint/documents.api';
import { CaseDocument } from '@/models/index';

export interface PickedFile {
  uri: string;
  name: string;
  type: string;
}

export async function uploadCaseFile(
  caseId: string,
  file: PickedFile,
  confidentiality: CaseDocument['confidentiality'] = 'Standard',
): Promise<CaseDocument> {
  // Swap in react-native-document-picker at the call site to obtain `file`;
  // kept decoupled here so this function is trivially testable.
  return uploadDocument(caseId, file, confidentiality);
}
