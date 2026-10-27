/**
 * End-to-end check of the offline queue against the REAL mock API (the Laravel stand-in).
 * Skipped unless a server is running:
 *
 *   npm run api:fresh                      (terminal 1)
 *   MOCK_API_URL=http://localhost:8000/api npx jest __tests__/offline/mockApi.e2e
 */
import AsyncStorage from '@react-native-async-storage/async-storage';
import NetInfo from '@react-native-community/netinfo';
import { apiClient, setAuthToken } from '@/api/client';
import { createCase, fetchCases } from '@/api/endpoint/cases.api';
import { createDarzation, fetchDiary } from '@/api/endpoint/darzations.api';
import { addNarration, fetchNarrationsForCase } from '@/api/endpoint/narrations.api';
import { setActiveUserId } from '@/offline/context';
import { listOutbox } from '@/offline/outbox';
import { prefetchAll } from '@/offline/prefetch';
import { flushOutbox } from '@/offline/syncEngine';
import { resetSyncStatus } from '@/offline/syncStatus';
import { beforeAll, describe, expect, it } from '@jest/globals';
import { jest } from '@jest/globals';

const URL = process.env.MOCK_API_URL;
const run = URL ? describe : describe.skip;
const mockedFetch = NetInfo.fetch as jest.Mock;
const goOffline = () => mockedFetch.mockResolvedValue({ isConnected: false, isInternetReachable: false } as never);
const goOnline = () => mockedFetch.mockResolvedValue({ isConnected: true, isInternetReachable: true } as never);
const sleep = (ms: number) => new Promise(r => setTimeout(r, ms));

run('offline queue against the mock API', () => {
  jest.setTimeout(60000);
  let userId = '';

  beforeAll(async () => {
    apiClient.defaults.baseURL = URL;
    // jest resolves axios' browser build; borrow the Node (http) adapter so it can reach localhost.
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    const actualAxios = jest.requireActual('axios/dist/node/axios.cjs') as {
      getAdapter: (adapter: string) => typeof apiClient.defaults.adapter;
    };

    apiClient.defaults.adapter = actualAxios.getAdapter('http'); await AsyncStorage.clear();
    const { data } = await apiClient.post('/auth/login', { email: 'ca@legalcms.test', password: 'password' });
    await setAuthToken(data.token);
    userId = data.user.id;
    setActiveUserId(userId);
    resetSyncStatus();
    goOnline();
  });

  it('downloads everything into the cache after sign-in', async () => {
    expect(await prefetchAll(true)).toBe(true);
    goOffline();
    const cases = await fetchCases();
    expect(cases.length).toBeGreaterThan(0);
    expect((await fetchDiary()).length).toBeGreaterThan(0);
    goOnline();
  });

  it('adds a case, a diary item and a narration offline, then pushes them with their original timestamp', async () => {
    const before = (await apiClient.get('/cases')).data.length;

    goOffline();
    const c = await createCase({ clientName: 'Offline Client (e2e)', matterType: 'Litigation', issueDescription: 'Created with no signal', priority: 'High' });
    const d = await createDarzation({
      caseId: c.id, ownerId: userId, title: 'Offline diary item', nextAction: 'Call back', nextActionDate: '2030-05-01', dueDate: '2030-05-01',
      priority: 'Medium', status: 'Pending', steps: [{ id: 's1', label: 'Draft', done: false }], assignedUserIds: [], visibleToRoles: ['Director', 'CA'],
    });
    const n = await addNarration({
      caseId: c.id, darzationId: d.id, authorId: userId, activityType: 'Call', title: 'Offline call', description: 'Spoke to client',
      timeSpentMinutes: 25, billable: true,
    });
    const savedAt = (await listOutbox()).at(-1)!.createdAt;
    expect((await listOutbox()).length).toHaveLength(3);
    expect((await fetchNarrationsForCase(c.id))[0].id).toBe(n.id); // visible locally while offline

    await sleep(1500); // the time between writing it and getting signal back
    goOnline();
    await flushOutbox();

    expect(await listOutbox()).toHaveLength(0);
    const cases = (await apiClient.get('/cases')).data;
    expect(cases.length).toHaveLength(before + 1);
    const real = cases.find((x: any) => x.clientName === 'Offline Client (e2e)');
    expect(real.assignedUserIds).toContain(userId);

    const narrations = (await apiClient.get(`/cases/${real.id}/narrations`)).data;
    expect(narrations).toHaveLength(1);
    expect(narrations[0].createdAt).toBe(savedAt); // kept its offline timestamp
    const diary = (await apiClient.get('/darzations')).data.find((x: any) => x.caseId === real.id);
    expect(narrations[0].darzationId).toBe(diary.id); // link survived the id swap
    expect(diary.steps[0].label).toBe('Draft');

    // and the on-device cache now holds the real records
    goOffline();
    expect((await fetchCases()).some(x => x.id === real.id)).toBe(true);
    expect((await fetchCases()).some(x => x.id === c.id)).toBe(false);
    goOnline();
  });

  it('does not create a duplicate when the first attempt reached the server but its reply was lost', async () => {
    const before = (await apiClient.get('/cases')).data.length;
    goOffline();
    await createCase({ clientName: 'Lost Reply (e2e)', matterType: 'Will', issueDescription: 'dup check' });
    const [entry] = await listOutbox();

    // The first attempt: server saves it, the app never sees the answer.
    await apiClient.request({ method: entry.method, url: entry.url, data: entry.body, headers: { 'Idempotency-Key': entry.id } });
    goOnline();
    await flushOutbox(); // replays the same request

    expect(await listOutbox()).toHaveLength(0);
    const after = (await apiClient.get('/cases')).data;
    expect(after.length).toHaveLength(before + 1);
    expect(after.filter((x: any) => x.clientName === 'Lost Reply (e2e)')).toHaveLength(1);
  });

  it('keeps a rejected change visible as failed instead of losing it', async () => {
    goOffline();
    // 2000+ characters is over the server limit for the narration description
    const c = (await fetchCases())[0];
    await addNarration({ caseId: c.id, authorId: userId, activityType: 'Call', description: 'x'.repeat(2500), timeSpentMinutes: 5, billable: false });
    goOnline();
    await flushOutbox();
    const [entry] = await listOutbox();
    expect(entry.status).toBe('failed');
    expect(entry.error).toMatch(/description/i);
  });
});
