import { configureStore } from '@reduxjs/toolkit';
import authReducer, { login, logout, setUser } from '@/features/auth/store/authSlice';
import * as authApi from '@/api/endpoint/auth.api';
import * as apiClient from '@/api/client';
import { beforeEach, describe, expect, it } from '@jest/globals';
import { jest } from '@jest/globals';                            

jest.mock('@api/endpoint/auth.api');
jest.mock('@api/client');

const mockedLogin = authApi.login as jest.Mock;
const mockedSetAuthToken = apiClient.setAuthToken as jest.Mock;
const mockedClearAuthToken = apiClient.clearAuthToken as jest.Mock;

function buildStore() {
  return configureStore({ reducer: { auth: authReducer } });
}

const sampleUser = {
  id: 'user-1',
  fullName: 'Thandiwe Mokoena',
  email: 'thandiwe@firm.co.za',
  role: 'CA' as const,
};

describe('authSlice reducer', () => {
  it('has the expected initial state', () => {
    const store = buildStore();
    expect(store.getState().auth).toEqual({ user: null, challenge: null, status: 'idle', error: null });
  });

  it('setUser populates the user and sets status to authenticated', () => {
    const store = buildStore();
    store.dispatch(setUser(sampleUser));
    expect(store.getState().auth.user).toEqual(sampleUser);
    expect(store.getState().auth.status).toBe('authenticated');
  });

  it('setUser(null) clears the user and resets status to idle', () => {
    const store = buildStore();
    store.dispatch(setUser(sampleUser));
    store.dispatch(setUser(null));
    expect(store.getState().auth.user).toBeNull();
    expect(store.getState().auth.status).toBe('idle');
  });
});

describe('login thunk', () => {
  beforeEach(() => {
    mockedLogin.mockReset();
    mockedSetAuthToken.mockReset().mockResolvedValue(undefined);
  });

  it('sets status to loading while pending', () => {
    mockedLogin.mockReturnValue(new Promise(() => {})); // never resolves
    const store = buildStore();
    store.dispatch(login({ email: 'a@b.com', password: 'x' }) as any);
    expect(store.getState().auth.status).toBe('loading');
  });

  it('populates user and sets status to authenticated on success', async () => {
    mockedLogin.mockResolvedValue({ token: 'tok-123', user: sampleUser });
    const store = buildStore();

    await store.dispatch(login({ email: 'thandiwe@firm.co.za', password: 'secret' }) as any);

    expect(store.getState().auth.user).toEqual(sampleUser);
    expect(store.getState().auth.status).toBe('authenticated');
    expect(mockedSetAuthToken).toHaveBeenCalledWith('tok-123');
  });

  it('sets status to error and records the message on failure', async () => {
    mockedLogin.mockRejectedValue(new Error('Invalid credentials'));
    const store = buildStore();

    await store.dispatch(login({ email: 'wrong@firm.co.za', password: 'bad' }) as any);

    expect(store.getState().auth.status).toBe('error');
    expect(store.getState().auth.error).toBe('Invalid credentials');
    expect(store.getState().auth.user).toBeNull();
  });
});

describe('logout thunk', () => {
  beforeEach(() => {
    mockedClearAuthToken.mockReset().mockResolvedValue(undefined);
  });

  it('clears the user, resets status, and clears the stored token', async () => {
    const store = buildStore();
    store.dispatch(setUser(sampleUser));

    await store.dispatch(logout() as any);

    expect(store.getState().auth.user).toBeNull();
    expect(store.getState().auth.status).toBe('idle');
    expect(mockedClearAuthToken).toHaveBeenCalledTimes(1);
  });
});
