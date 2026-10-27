/**
 * Documents attached to a specific narration entry (not just the parent case).
 *
 * Files are always copied into the app's own sandboxed storage first
 * (expo-file-system, a free, on-device, no-account-needed API) so the
 * attachment works even offline or before the backend has a matching
 * narration-documents route. A remote upload is then attempted best-effort —
 * on success the document is marked `synced`; otherwise it stays safely on
 * the device as `pending-sync` and can be retried later.
 */
import * as FileSystem from 'expo-file-system/legacy';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { CaseDocument } from '@/models/index';
import { uploadDocument } from '@/api/endpoint/documents.api';
import { isTempId } from '@/offline/ids';
import { onEntryDiscarded, onEntrySynced } from '@/offline/syncEngine';
import { isDeviceOffline } from '@/offline/connectivity';

const REGISTRY_KEY_PREFIX = 'legalcms_narration_documents_';

export interface PickedDocumentFile {
  uri: string;
  name: string;
  mimeType?: string;
  size?: number;
}

function registryKey(narrationId: string): string {
  return `${REGISTRY_KEY_PREFIX}${narrationId}`;
}

export async function getDocumentsForNarration(narrationId: string): Promise<CaseDocument[]> {
  const raw = await AsyncStorage.getItem(registryKey(narrationId));
  return raw ? (JSON.parse(raw) as CaseDocument[]) : [];
}

async function saveRegistry(narrationId: string, docs: CaseDocument[]): Promise<void> {
  await AsyncStorage.setItem(registryKey(narrationId), JSON.stringify(docs));
}

export async function attachDocumentToNarration(params: {
  caseId: string;
  narrationId: string;
  file: PickedDocumentFile;
  confidentiality: CaseDocument['confidentiality'];
  uploadedById: string;
}): Promise<CaseDocument> {
  const { caseId, narrationId, file, confidentiality, uploadedById } = params;

  const dir = `${FileSystem.documentDirectory}narration-documents/${narrationId}/`;
  await FileSystem.makeDirectoryAsync(dir, { intermediates: true }).catch(() => { });
  const localUri = `${dir}${Date.now()}_${file.name}`;
  await FileSystem.copyAsync({ from: file.uri, to: localUri });

  const existing = await getDocumentsForNarration(narrationId);
  const doc: CaseDocument = {
    id: `local-${Date.now()}`,
    caseId,
    narrationId,
    fileName: file.name,
    version: existing.filter(d => d.fileName === file.name).length + 1,
    confidentiality,
    uploadedById,
    uploadedAt: new Date().toISOString(),
    uri: localUri,
    localUri,
    mimeType: file.mimeType,
    sizeBytes: file.size,
    syncStatus: 'local-only',
  };

  // A narration or case that only exists on this device can't be uploaded to yet — it stays pending
  // and goes up (against the real ids) as soon as its parent has synced.
  const canUploadNow = !isTempId(narrationId) && !isTempId(caseId) && !(await isDeviceOffline());
  try {
    if (!canUploadNow) {
      throw new Error('deferred');
    }
    const remote = await uploadDocument(
      caseId,
      { uri: localUri, name: file.name, type: file.mimeType ?? 'application/octet-stream' },
      confidentiality,
      narrationId,
    );
    doc.uri = remote.uri ?? doc.uri;
    doc.syncStatus = 'synced';
  } catch {
    doc.syncStatus = 'pending-sync';
  }

  const updated = [...existing, doc];
  await saveRegistry(narrationId, updated);
  return doc;
}

/** Retries any documents still waiting to reach the backend (call on app start / pull-to-refresh). */

export async function retryPendingSync(
  caseId: string,
  narrationId: string,
): Promise<void> {
  if (
    isTempId(narrationId) ||
    isTempId(caseId) ||
    (await isDeviceOffline())
  ) {
    return;
  }

  const docs = await getDocumentsForNarration(narrationId);

  const pendingDocs = docs.filter(
    doc =>
      doc.syncStatus === 'pending-sync' ||
      doc.syncStatus === 'sync-failed',
  );

  const results = await Promise.all(
    pendingDocs.map(async doc => {
      try {
        const remote = await uploadDocument(
          caseId,
          {
            uri: doc.localUri ?? doc.uri,
            name: doc.fileName,
            type: doc.mimeType ?? 'application/octet-stream',
          },
          doc.confidentiality,
          narrationId,
        );

        return {
          doc,
          uri: remote.uri ?? doc.uri,
          syncStatus: 'synced' as const,
        };
      } catch {
        return {
          doc,
          uri: doc.uri,
          syncStatus: 'sync-failed' as const,
        };
      }
    }),
  );

  for (const result of results) {
    result.doc.uri = result.uri;
    result.doc.syncStatus = result.syncStatus;
  }

  if (results.length > 0) {
    await saveRegistry(narrationId, docs);
  }
}


export async function removeNarrationDocument(narrationId: string, documentId: string): Promise<void> {
  const docs = await getDocumentsForNarration(narrationId);
  const target = docs.find(d => d.id === documentId);
  if (target?.localUri) {
    await FileSystem.deleteAsync(target.localUri, { idempotent: true }).catch(() => { });
  }
  await saveRegistry(narrationId, docs.filter(d => d.id !== documentId));
}

/** Moves a narration's attachments from its temporary id to the real one after it has synced. */
async function migrateRegistry(tempId: string, realId: string, realCaseId: string): Promise<void> {
  const docs = await getDocumentsForNarration(tempId);
  if (docs.length === 0) {
    return;
  }
  // localUri is kept as-is: the files stay where they were copied to.
  await saveRegistry(realId, docs.map(d => ({ ...d, narrationId: realId, caseId: realCaseId })));
  await AsyncStorage.removeItem(registryKey(tempId));
}

/** Uploads every attachment still waiting (call after a sync or when the connection returns). */

export async function retryAllPendingNarrationDocuments(): Promise<void> {
  const keys = (await AsyncStorage.getAllKeys()).filter(
    key => key.startsWith(REGISTRY_KEY_PREFIX),
  );

  const narrationIds = keys
    .map(key => key.slice(REGISTRY_KEY_PREFIX.length))
    .filter(narrationId => !isTempId(narrationId));

  await Promise.all(
    narrationIds.map(async narrationId => {
      const docs = await getDocumentsForNarration(narrationId);
      const waiting = docs.find(
        doc => doc.syncStatus !== 'synced',
      );

      if (waiting) {
        await retryPendingSync(waiting.caseId, narrationId);
      }
    }),
  );
}


let registered = false;

/** Hooks attachments into the sync engine. Idempotent; call once at app start. */
export function registerNarrationDocumentSync(): void {
  if (registered) {
    return;
  }
  registered = true;
  onEntrySynced(async (entry, record) => {
    if (entry.entity === 'narration' && entry.op === 'create') {
      await migrateRegistry(entry.entityId, String(record.id), String(record.caseId));
    }
  });
  onEntryDiscarded(async entry => {
    if (entry.entity !== 'narration' || entry.op !== 'create') {
      return;
    }
    for (const doc of await getDocumentsForNarration(entry.entityId)) {
      removeNarrationDocument(entry.entityId, doc.id);
    }
  });
}
