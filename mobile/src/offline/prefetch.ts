import { fetchCases } from '@/api/endpoint/cases.api';
import { fetchDiary } from '@/api/endpoint/darzations.api';
import { fetchDocumentsForCase } from '@/api/endpoint/documents.api';
import { fetchNarrationsForCase } from '@/api/endpoint/narrations.api';
import { fetchUsers } from '@/api/endpoint/users.api';
import { cacheMeta, updateCacheMeta } from './cache';
import { getActiveUserId } from './context';
import { isDeviceOffline } from './connectivity';
import { setSyncStatus } from './syncStatus';

const MIN_INTERVAL_MS = 5 * 60 * 1000;
const MAX_CASES = 150; // keeps the on-device cache comfortably inside AsyncStorage's size limits
const PARALLEL = 4;

let running: Promise<boolean> | null = null;

/**
 * Downloads everything the signed-in user can see — cases, diary, users, and each case's narrations
 * and document list — into the cache so the app keeps working without a connection. Each fetch
 * function writes its own result to the cache, so this just calls them. (Document *files* are not
 * downloaded, only their list; narration attachments added on this device are already stored locally.)
 */
export function prefetchAll(force = false): Promise<boolean> {
    if (running) {
        return running;
    }
    running = (async () => {
        if (!getActiveUserId() || (await isDeviceOffline())) {
            return false;
        }
        const meta = await cacheMeta();
        if (!force && meta.lastPrefetchAt && Date.now() - new Date(meta.lastPrefetchAt).getTime() < MIN_INTERVAL_MS) {
            return false;
        }
        try {
            const [cases] = await Promise.all([fetchCases(), fetchDiary(), fetchUsers().catch(() => [])]);
            const ids = cases.filter(c => !c.id.startsWith('tmp_')).slice(0, MAX_CASES).map(c => c.id);
            for (let i = 0; i < ids.length; i += PARALLEL) {
                await Promise.all(
                    ids.slice(i, i + PARALLEL).flatMap(id => [
                        fetchNarrationsForCase(id).catch(() => []),
                        fetchDocumentsForCase(id).catch(() => []),
                    ]),
                );
            }
            const now = new Date().toISOString();
            await updateCacheMeta({ lastPrefetchAt: now });
            setSyncStatus({ lastError: null });
            return true;
        } catch {
            return false;
        }
    })().finally(() => {
        running = null;
    });
    return running;
}
