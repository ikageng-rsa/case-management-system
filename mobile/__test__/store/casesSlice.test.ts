import { configureStore } from '@reduxjs/toolkit';
import casesReducer, { loadCases } from '@/features/cases/store/casesSlice';
import * as casesApi from '@/api/endpoint/cases.api';
import { beforeEach, describe, expect, it } from '@jest/globals';
import { jest } from '@jest/globals';                            

jest.mock('@api/endpoints/cases.api');

const mockedFetchCases = casesApi.fetchCases as jest.Mock;

function buildStore() {
  return configureStore({ reducer: { cases: casesReducer } });
}

const sampleCase = {
  id: 'case-1',
  fileReference: 'TM/LIT/014/2026',
  clientId: 'client-1',
  clientName: 'Acme Corp',
  matterType: 'Litigation',
  status: 'Open' as const,
  leadAttorneyId: 'user-1',
  openedDate: '2026-08-22',
};

describe('casesSlice reducer', () => {
  it('has the expected initial state', () => {
    const store = buildStore();
    expect(store.getState().cases).toEqual({ items: [], status: 'idle', error: null });
  });
});

describe('loadCases thunk', () => {
  beforeEach(() => {
    mockedFetchCases.mockReset();
  });

  it('sets status to loading while pending', () => {
    mockedFetchCases.mockReturnValue(new Promise(() => {}));
    const store = buildStore();
    store.dispatch(loadCases() as any);
    expect(store.getState().cases.status).toBe('loading');
  });

  it('populates items and sets status to loaded on success', async () => {
    mockedFetchCases.mockResolvedValue([sampleCase]);
    const store = buildStore();

    await store.dispatch(loadCases() as any);

    expect(store.getState().cases.items).toEqual([sampleCase]);
    expect(store.getState().cases.status).toBe('loaded');
  });

  it('passes filter params through to fetchCases', async () => {
    mockedFetchCases.mockResolvedValue([]);
    const store = buildStore();

    await store.dispatch(loadCases({ status: 'Open', search: 'Acme' }) as any);

    expect(mockedFetchCases).toHaveBeenCalledWith({ status: 'Open', search: 'Acme' });
  });

  it('sets status to error and records the message on failure', async () => {
    mockedFetchCases.mockRejectedValue(new Error('Network error'));
    const store = buildStore();

    await store.dispatch(loadCases() as any);

    expect(store.getState().cases.status).toBe('error');
    expect(store.getState().cases.error).toBe('Network error');
    expect(store.getState().cases.items).toEqual([]);
  });
});
