import { apiClient } from '@/api/client';
import { Document, indexIncluded, Resource } from '@/api/jsonapi';
import { isLocalDiaryId, mapDiaryEntry, mapDiaryList } from '@/api/mappers/diary';
import { fetchAllPage } from '@/api/pagination';
import { Darzation } from '@/models/index';
import { CacheNames, cacheUpsert } from '@/offline/cache';
import { isDeviceOffline, isTransientError } from '@/offline/connectivity';
import { OfflineNotAllowedError } from '@/offline/policy';
import { cachedList, submitCreate, submitUpdate } from '@/offline/repository';

/** `remind_before_minutes` limits from the guide: 5 minutes to 30 days; null = no reminder; left out = the user's usual. */
export const REMINDER_MIN_MINUTES = 5;
export const REMINDER_MAX_MINUTES = 43_200;

const entryUrl = (matterId: string, entryId?: string, suffix = '') =>
  `/matters/${matterId}/diary-entries${entryId ? `/${entryId}` : ''}${suffix}`;

/**
 * The signed-in user's open diary: what is pending plus what is overdue, soonest first (the server keeps to the
 * user's own entries on matters they can still see). Kept on the phone so the diary reads offline.
 */
export async function fetchDiary(): Promise<Darzation[]> {
  return cachedList<Darzation>({
    name: CacheNames.diary,
    entity: 'darzation',
    request: async () => {
      const [pending, overdue] = await Promise.all([
        fetchAllPage('/diary-entries', { show: 'pending' }),
        fetchAllPage('/diary-entries', { show: 'overdue' }),
      ]);
      const seen = new Set<string>();
      const rows: Darzation[] = [];
      for (const d of [...mapDiaryList(pending.data, pending.included), ...mapDiaryList(overdue.data, overdue.included)]) {
        if (!seen.has(d.id)) {
          seen.add(d.id);
          rows.push(d);
        }
      }
      return rows.sort((a, b) => (a.dueAt ?? a.dueDate).localeCompare(b.dueAt ?? b.dueDate));
    },
  });
}

/** What was done lately: the newest page of completed entries (most recently done first). */
export async function fetchCompletedDiary(): Promise<Darzation[]> {
  return cachedList<Darzation>({
    name: CacheNames.diaryCompleted,
    request: async () => {
      const { data, included } = await fetchAllPage('/diary-entries', { show: 'completed' }, 1);
      return mapDiaryList(data, included);
    },
  });
}

/** Entries for one day ("My day"): `from` and `to` are the same calendar date, e.g. 2026-11-11. */
export async function fetchDiaryForDay(day: string): Promise<Darzation[]> {
  const { data, included } = await fetchAllPage('/diary-entries', { show: 'pending', from: day, to: day });
  return mapDiaryList(data, included);
}

/**
 * Everyone's entries on one matter. `show` is pending, completed, overdue or mine (the user's own pending); `open`
 * is pending plus overdue. Online it asks the server; with no connection it falls back to the user's own entries
 * from the on-device diary.
 */
export async function fetchMatterDiary(
  matterId: string,
  show: 'open' | 'pending' | 'completed' | 'overdue' | 'mine' = 'open',
): Promise<Darzation[]> {
  if (!isLocalDiaryId(matterId) && !(await isDeviceOffline())) {
    try {
      const shows = show === 'open' ? ['pending', 'overdue'] : [show];
      const pages = await Promise.all(shows.map(s => fetchAllPage(entryUrl(matterId), { show: s })));
      const seen = new Set<string>();
      return pages
        .flatMap(p => mapDiaryList(p.data, p.included, matterId))
        .filter(d => !seen.has(d.id) && seen.add(d.id))
        .sort((x, y) => (x.dueAt ?? x.dueDate).localeCompare(y.dueAt ?? y.dueDate));
    } catch (error) {
      if (!isTransientError(error)) {
        throw error;
      }
    }
  }
  return (await fetchDiary()).filter(d => d.caseId === matterId);
}

/**
 * v1 has no single-entry route, so the entry is looked for in the user's open diary, then in the recently completed
 * list. "My diary" only holds entries assigned to the user, so when the matter is known (an entry just added for a
 * colleague, or one opened from the matter page) its own diary is searched last.
 */
export async function fetchDarzationById(id: string, matterId?: string): Promise<Darzation> {
  const open = (await fetchDiary()).find(d => d.id === id);
  if (open) {
    return open;
  }
  const done = (await fetchCompletedDiary().catch(() => [] as Darzation[])).find(d => d.id === id);
  if (done) {
    return done;
  }
  if (matterId) {
    for (const show of ['open', 'completed'] as const) {
      const found = (await fetchMatterDiary(matterId, show).catch(() => [] as Darzation[])).find(d => d.id === id);
      if (found) {
        return found;
      }
    }
  }
  throw new Error('Diary entry not found.');
}

export async function fetchOverdueDarzations(): Promise<Darzation[]> {
  return (await fetchDiary()).filter(d => d.overdue);
}

export interface NewDiaryEntry {
  caseId: string;
  body: string;
  /** ISO moment with its offset, e.g. 2026-11-14T09:00:00+02:00. */
  dueAt: string;
  /** Defaults to the person diarising. */
  assignedTo?: string;
  /** undefined = the user's usual reminder, null = none. */
  remindBeforeMinutes?: number | null;
  /** For the on-device copy shown until the server confirms. */
  ownerId: string;
  assigneeName?: string;
}

/** Adds an entry to a matter's diary. Works offline: it is queued and sent when the phone is back online. */
export async function createDarzation(input: NewDiaryEntry): Promise<Darzation> {
  const assignee = input.assignedTo ?? input.ownerId;
  return submitCreate<Darzation>({
    entity: 'darzation',
    label: `New diary entry — ${input.body.slice(0, 40)}`,
    url: entryUrl(input.caseId),
    body: {
      body: input.body,
      due_at: input.dueAt,
      ...(input.assignedTo ? { assigned_to: input.assignedTo } : {}),
      ...(input.remindBeforeMinutes !== undefined ? { remind_before_minutes: input.remindBeforeMinutes } : {}),
    },
    optimistic: id => ({
      id,
      caseId: input.caseId,
      ownerId: assignee,
      title: input.body,
      nextAction: input.body,
      nextActionDate: input.dueAt.slice(0, 10),
      dueDate: input.dueAt.slice(0, 10),
      priority: 'Medium',
      status: 'Pending',
      steps: [],
      assignedUserIds: assignee ? [assignee] : [],
      overdue: false,
      visibleToRoles: ['Admin', 'Director', 'CA', 'Secretary', 'Messenger'],
      body: input.body,
      dueAt: input.dueAt,
      assigneeId: assignee,
      assigneeName: input.assigneeName,
      remindBeforeMinutes: input.remindBeforeMinutes ?? null,
      narrationId: null,
    }),
    listName: () => CacheNames.diary,
  });
}

export interface DiaryEntryChanges {
  body: string;
  dueAt: string;
  assignedTo: string;
  /** A narration on this matter, or null to remove the link. */
  narrationId: string | null;
}

/** Edits an entry. The API wants every field each time. Not possible offline, and not once the entry is done (409). */
export async function updateDarzation(entry: Pick<Darzation, 'id' | 'caseId'>, changes: DiaryEntryChanges): Promise<Darzation> {
  return submitUpdate<Darzation>({
    entity: 'darzation',
    entityId: entry.id,
    action: 'Editing a diary entry',
    label: `Edit diary entry — ${changes.body.slice(0, 40)}`,
    url: entryUrl(entry.caseId, entry.id),
    body: { body: changes.body, due_at: changes.dueAt, assigned_to: changes.assignedTo, narration_id: changes.narrationId },
    patch: {
      kind: 'merge',
      fields: {
        body: changes.body,
        title: changes.body,
        nextAction: changes.body,
        dueAt: changes.dueAt,
        dueDate: changes.dueAt.slice(0, 10),
        nextActionDate: changes.dueAt.slice(0, 10),
        assigneeId: changes.assignedTo,
        assignedUserIds: [changes.assignedTo],
        narrationId: changes.narrationId,
      },
    },
    listName: CacheNames.diary,
  });
}

/** Changes when the reminder goes out (null = none; 5 to 43 200 minutes). 403 unless the entry is the user's own. */
export async function updateDiaryReminder(entry: Pick<Darzation, 'id' | 'caseId'>, minutes: number | null): Promise<Darzation> {
  return submitUpdate<Darzation>({
    entity: 'darzation',
    entityId: entry.id,
    action: 'Changing a reminder',
    label: 'Change diary reminder',
    url: entryUrl(entry.caseId, entry.id, '/reminder'),
    body: { remind_before_minutes: minutes },
    patch: { kind: 'merge', fields: { remindBeforeMinutes: minutes } },
    listName: CacheNames.diary,
  });
}

/**
 * Marks an entry done. Only from its due time on (otherwise 409 with the reason), and only online. Completing an
 * entry that is already done answers 200 and changes nothing.
 */
export async function completeDarzation(entry: Pick<Darzation, 'id' | 'caseId'>): Promise<Darzation> {
  if (isLocalDiaryId(entry.id) || (await isDeviceOffline())) {
    throw new OfflineNotAllowedError('Marking a diary entry done');
  }
  const { data: doc } = await apiClient.post<Document<Resource>>(entryUrl(entry.caseId, entry.id, '/complete'));
  const done = mapDiaryEntry(doc.data as Resource, indexIncluded(doc.included), entry.caseId);
  await cacheUpsert(CacheNames.diaryCompleted, done);
  return done;
}
