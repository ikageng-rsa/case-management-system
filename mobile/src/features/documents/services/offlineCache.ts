/**
 * Offline document cache — intentionally conservative for POPIA/FICA reasons:
 * only "Standard" confidentiality documents may be cached to disk. Anything
 * "Restricted" or "Privileged" must stream on demand and never be written to
 * device storage. Swap AsyncStorage for a proper encrypted store (e.g.
 * react-native-encrypted-storage or SQLCipher via WatermelonDB) before this
 * touches real client files.
 */
import AsyncStorage from '@react-native-async-storage/async-storage';
import { CaseDocument } from '@/models/index';

const CACHE_KEY_PREFIX = 'legalcms_doc_cache_';

export function isCacheable(doc: CaseDocument): boolean {
  return doc.confidentiality === 'Standard';
}

export async function cacheDocumentMeta(doc: CaseDocument): Promise<void> {
  if (!isCacheable(doc)) {
    return;
  }
  await AsyncStorage.setItem(`${CACHE_KEY_PREFIX}${doc.id}`, JSON.stringify(doc));
}

export async function getCachedDocumentMeta(docId: string): Promise<CaseDocument | null> {
  const raw = await AsyncStorage.getItem(`${CACHE_KEY_PREFIX}${docId}`);
  return raw ? JSON.parse(raw) : null;
}

export async function clearDocumentCache(docId: string): Promise<void> {
  await AsyncStorage.removeItem(`${CACHE_KEY_PREFIX}${docId}`);
}
