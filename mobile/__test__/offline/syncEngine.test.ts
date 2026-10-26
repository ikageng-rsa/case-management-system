import AsyncStorage from '@react-native-async-storage/async-storage';
import NetInfo from '@react-native-community/netinfo';
import { apiClient } from '@/api/client';
import { createCase, fetchCases, updateCase } from '@/api/endpoint/cases.api';
import { createDarzation, fetchDiary } from '@/api/endpoint/darzations.api';
import { addNarration, fetchNarrationsForCase } from '@/api/endpoint/narrations.api';
import { setActiveUserId } from '@/offline/context';
import { listOutbox } from '@/offline/outbox';
import { discardEntry, flushOutbox, retryEntry } from '@/offline/syncEngine';
import { isTempId } from '@/offline/ids';
import { getSyncSnapshot, resetSyncStatus } from '@/offline/syncStatus';
import { beforeEach, describe, expect, it } from '@jest/globals';
import { jest } from '@jest/globals';

jest.mock('@api/client', () => ({
  apiClient: { get: jest.fn(), post: jest.fn(), patch: jest.fn(), request: jest.fn() },
}));

const mockedFetch = NetInfo.fetch as jest.Mock;
const api = apiClient as unknown as Record<'get' | 'post' | 'patch' | 'request', jest.Mock>;

const goOffline = () => mockedFetch.mockResolvedValue({ isConnected: false, isInternetReachable: false } as never);
const goOnline = () => mockedFetch.mockResolvedValue({ isConnected: true, isInternetReachable: true } as never);
const networkError = () => Object.assign(new Error('Network Error'), { isAxiosError: true, response: undefined });
const httpError = (status: number, data: unknown) =>
  Object.assign(new Error(`HTTP ${status}`), { isAxiosError: true, response: { status, data } });

const caseBody = { clientName: 'Mokoena', matterType: 'Litigation', issueDescription: 'RAF claim' };

beforeEach(async () => {
  await AsyncStorage.clear();
  jest.clearAllMocks();
  resetSyncStatus();
  setActiveUserId('user-1');
  goOnline();
});

describe('working offline', () => {
  it('queues a new case, shows it in the list and keeps it for the creator', async () => {
    goOffline();
    const created = await createCase(caseBody);

    expect(isTempId(created.id)).toBe(true);
    expect(created.syncState).toBe('pending');
    expect(created.assignedUserIds).toContain('user-1');
    expect(api.post).not.toHaveBeenCalled();

    const list = await fetchCases();
    expect(list.map(c => c.id)).toEqual([created.id]);
    expect(await listOutbox()).toHaveLength(1);
  });

  it('serves the last downloaded data when the connection is gone', async () => {
    api.get.mockResolvedValueOnce({ data: [{ id: 'c1', clientName: 'Existing', matterType: 'Will', status: 'Open' }] } as never);
    await fetchCases();

    goOffline();
    const list = await fetchCases();
    expect(list).toHaveLength(1);
    expect(list[0].clientName).toBe('Existing');
    expect(api.get).toHaveBeenCalledTimes(1);
  });

  it('falls back to the cache (and queues a create) when the request never gets an answer', async () => {
    api.get.mockResolvedValueOnce({ data: [{ id: 'c1', clientName: 'Existing', matterType: 'Will', status: 'Open' }] } as never);
    await fetchCases();

    api.get.mockRejectedValueOnce(networkError() as never);
    expect((await fetchCases()).map(c => c.id)).toEqual(['c1']);

    api.post.mockRejectedValueOnce(networkError() as never);
    const created = await createCase(caseBody);
    expect(created.syncState).toBe('pending');
    expect(await listOutbox()).toHaveLength(1);
  });

  it('does not queue when the server answers with a validation error', async () => {
    api.post.mockRejectedValueOnce(httpError(422, { message: 'The client name field is required.' }) as never);
    await expect(createCase(caseBody)).rejects.toBeTruthy();
    expect(await listOutbox()).toHaveLength(0);
  });

  it('does not allow editing existing records offline (default policy)', async () => {
    goOffline();
    await expect(updateCase('c1', { notes: 'x' })).rejects.toThrow(/offline/i);
    expect(await listOutbox()).toHaveLength(0);
  });

  it('keeps narrations written against a case that only exists on the device', async () => {
    goOffline();
    const c = await createCase(caseBody);
    const n = await addNarration({
      caseId: c.id,
      authorId: 'user-1',
      activityType: 'Call',
      description: 'Called client',
      timeSpentMinutes: 15,
      billable: true,
    });
    expect(n.syncState).toBe('pending');
    expect((await fetchNarrationsForCase(c.id)).map(x => x.id)).toEqual([n.id]);
  });
});

describe('syncing when the connection returns', () => {
  it('sends queued items oldest-first, swaps temporary ids for real ones and empties the queue', async () => {
    goOffline();
    const c = await createCase(caseBody);
    const d = await createDarzation({
      caseId: c.id,
      ownerId: 'user-1',
      title: 'File summons',
      nextAction: 'File',
      nextActionDate: '2030-01-01',
      dueDate: '2030-01-01',
      priority: 'High',
      status: 'Pending',
      steps: [],
      assignedUserIds: [],
      visibleToRoles: ['Director'],
    });
    const n = await addNarration({
      caseId: c.id,
      darzationId: d.id,
      authorId: 'user-1',
      activityType: 'Draft',
      description: 'Drafted summons',
      timeSpentMinutes: 60,
      billable: true,
    });
    const entries = await listOutbox();
    expect(entries.map(e => e.entity)).toEqual(['case', 'darzation', 'narration']);

    goOnline();
    api.request.mockImplementation(async (req: any) => {
      if (req.url === '/cases') return { data: { ...caseBody, id: 'real-case', status: 'Open', fileReference: 'MOK-1' } };
      if (req.url === '/darzations') return { data: { ...JSON.parse(JSON.stringify(req.data)), id: 'real-diary' } };
      return { data: { ...req.data, id: 'real-narr', caseId: 'real-case', createdAt: req.data.clientCreatedAt } };
    });
    await flushOutbox();

    type SentRequest = {
      url: string;
      data: {
        caseId?: string;
        darzationId?: string;
        clientCreatedAt?: string;
      };
      headers: Record<string, string>;
    };

    const sent = api.request.mock.calls.map(
      ([r]) => r as SentRequest
    );

    expect(sent.map(r => r.url)).toEqual([
      '/cases',
      '/darzations',
      '/cases/real-case/narrations',
    ]);

    expect(sent[1].data.caseId).toBe('real-case');
    expect(sent[2].data.darzationId).toBe('real-diary');

    expect(sent.map(r => r.headers['Idempotency-Key'])).toEqual(
      entries.map(e => e.id)
    );

    expect(sent[2].data.clientCreatedAt).toBe(entries[2].createdAt);

    expect(await listOutbox()).toHaveLength(0);

    // The cache now holds the server's records, not the temporary ones.
    goOffline();
    expect((await fetchCases()).map(x => x.id)).toEqual(['real-case']);
    expect((await fetchDiary()).map(x => x.id)).toEqual(['real-diary']);
    expect((await fetchNarrationsForCase('real-case')).map(x => x.id)).toEqual(['real-narr']);
    expect([c.id, d.id, n.id].every(isTempId)).toBe(true);
  });

  it('stops at a network error and leaves everything queued', async () => {
    goOffline();
    await createCase(caseBody);
    await createCase({ ...caseBody, clientName: 'Second' });
    goOnline();
    api.request.mockRejectedValue(networkError() as never);
    await flushOutbox();
    expect(api.request).toHaveBeenCalledTimes(1);
    const left = await listOutbox();
    expect(left).toHaveLength(2);
    expect(left.every(e => e.status === 'pending')).toBe(true);
  });

  it('marks a rejected item as failed, holds what depends on it, and discard removes both', async () => {
    goOffline();
    const c = await createCase(caseBody);
    await addNarration({ caseId: c.id, authorId: 'user-1', activityType: 'Call', description: 'x', timeSpentMinutes: 0, billable: false });
    await createCase({ ...caseBody, clientName: 'Unrelated' });
    goOnline();
    api.request.mockImplementation(async (req: any) => {
      if (req.data.clientName === 'Mokoena') throw httpError(422, { message: 'Rejected', errors: { clientName: ['Nope.'] } });
      return { data: { ...req.data, id: 'real-other' } };
    });
    await flushOutbox();

    const entries = await listOutbox();
    // The unrelated case behind them in the queue still went through.
    expect(entries.map(e => e.status)).toEqual(['failed', 'blocked']);
    expect(entries[0].error).toBe('Nope.');

    // A narration waiting on the failed case is never sent against a made-up id.
    expect(api.request.mock.calls.every(([r]: any) => !r.url.includes('tmp_'))).toBe(true);

    await discardEntry(entries[0].id);
    const remaining = await listOutbox();
    expect(remaining).toHaveLength(0);
  });

  it('retry puts a failed item back in the queue', async () => {
    goOffline();
    await createCase(caseBody);
    goOnline();
    api.request.mockRejectedValueOnce(httpError(422, { message: 'Rejected' }) as never);
    await flushOutbox();
    const [failed] = await listOutbox();
    expect(failed.status).toBe('failed');

    api.request.mockResolvedValueOnce({ data: { ...caseBody, id: 'ok-1' } } as never);
    await retryEntry(failed.id);
    expect(await listOutbox()).toHaveLength(0);
  });

  it('flags an expired login instead of failing the item', async () => {
    goOffline();
    await createCase(caseBody);
    goOnline();
    api.request.mockRejectedValueOnce(httpError(401, { message: 'Unauthenticated.' }) as never);
    await flushOutbox();
    const [entry] = await listOutbox();
    expect(entry.status).toBe('pending');
    expect(getSyncSnapshot().authRequired).toBe(true);
  });

  it('keeps each user\'s queue separate', async () => {
    goOffline();
    await createCase(caseBody);
    setActiveUserId('user-2');
    expect(await listOutbox()).toHaveLength(0);
    setActiveUserId('user-1');
    expect(await listOutbox()).toHaveLength(1);
  });
});
