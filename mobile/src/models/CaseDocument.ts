export type DocumentConfidentiality = 'Standard' | 'Restricted' | 'Privileged';

// local-only: saved to on-device storage, never reached the backend (offline capture,
//   or no narration/document endpoint is wired up server-side yet).
// pending-sync: queued to upload next time the backend is reachable.
// synced: confirmed stored on the server.
// sync-failed: the last upload attempt errored; still safe on-device.
export type DocumentSyncStatus = 'local-only' | 'pending-sync' | 'synced' | 'sync-failed';

export interface CaseDocument {
  id: string;
  caseId: string;
  // When set, this document is linked to a specific narration entry rather
  // than just the case in general.
  narrationId?: string;
  fileName: string;
  version: number;
  confidentiality: DocumentConfidentiality;
  uploadedById: string;
  uploadedAt: string;
  uri: string; // remote URI once synced (signed URL / storage path)
  localUri?: string; // on-device copy under expo-file-system's documentDirectory
  mimeType?: string;
  sizeBytes?: number;
  syncStatus: DocumentSyncStatus;
  cachedOffline?: boolean;
}
