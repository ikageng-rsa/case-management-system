import { uploadDocument, UploadFile } from '@/api/endpoint/documents.api';
import { CaseDocument } from '@/models/index';

export type PickedFile = UploadFile;

/** Kept apart from the screens so it is easy to test; `file` comes from the document picker at the call site. */
export async function uploadCaseFile(caseId: string, file: PickedFile, kind: string, name?: string): Promise<CaseDocument> {
  return uploadDocument(caseId, file, kind, name);
}
