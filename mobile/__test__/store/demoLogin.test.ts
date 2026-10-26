import { configureStore } from '@reduxjs/toolkit';
import authReducer, { login } from '@/features/auth/store/authSlice';
import * as authApi from '@/api/endpoint/auth.api';
import * as apiClient from '@/api/client';
import { DEMO_CREDENTIALS, DEMO_TOKEN, DEMO_USER } from '@/constants/demoUser';
import { beforeEach, describe, expect, it } from '@jest/globals';
import { jest } from '@jest/globals';                            

jest.mock('@api/endpoint/auth.api');
jest.mock('@api/client');

const mockedLogin = authApi.login as jest.Mock;
const mockedSetAuthToken = apiClient.setAuthToken as jest.Mock;

const buildStore = () => configureStore({ reducer: { auth: authReducer } });

describe('demo login', () => {
  beforeEach(() => {jest.clearAllMocks()});

  it('signs in the demo user locally without calling the API', async () => {
    const store = buildStore();
    await store.dispatch(login(DEMO_CREDENTIALS));

    expect(mockedLogin).not.toHaveBeenCalled();
    expect(mockedSetAuthToken).toHaveBeenCalledWith(DEMO_TOKEN);
    expect(store.getState().auth.user).toEqual(DEMO_USER);
    expect(store.getState().auth.status).toBe('authenticated');
  });

  it('is case/whitespace tolerant on the email', async () => {
    const store = buildStore();
    await store.dispatch(login({ email: `  ${DEMO_CREDENTIALS.email.toUpperCase()} `, password: DEMO_CREDENTIALS.password }));
    expect(mockedLogin).not.toHaveBeenCalled();
    expect(store.getState().auth.user).toEqual(DEMO_USER);
  });

  it('sends a wrong demo password to the real API instead', async () => {
    mockedLogin.mockRejectedValueOnce(new Error('Invalid credentials') as never);
    const store = buildStore();
    await store.dispatch(login({ email: DEMO_CREDENTIALS.email, password: 'wrong' }));

    expect(mockedLogin).toHaveBeenCalledWith(DEMO_CREDENTIALS.email, 'wrong');
    expect(store.getState().auth.user).toBeNull();
  });
});
