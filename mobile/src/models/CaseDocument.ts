/** Where a document stands: the file is only on this phone, queued to upload, uploaded, or the last upload failed. */
export type DocumentSyncStatus = 'local-only' | 'pending-sync' | 'synced' | 'sync-failed';

/** One entry of GET /document-kinds: mandate, evidence, court_order… */
export interface DocumentKind {
  id: string;
  name: string;
}

export interface CaseDocument {
  id: string;
  caseId: string;
  /** Set when the file was attached while logging a narration. The server has no such link, so it is kept on this device. */
  narrationId?: string;
  /** How the document is listed. */
  name: string;
  fileName: string;
  /** A document kind id, see DocumentKind. */
  kind: string;
  uploadedAt: string;
  /** Local copy under the app's own storage, for files attached on this phone. */
  localUri?: string;
  mimeType?: string;
  sizeBytes?: number;
  syncStatus: DocumentSyncStatus;
}
