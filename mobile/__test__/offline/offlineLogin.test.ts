import { configureStore } from '@reduxjs/toolkit';
import AsyncStorage from '@react-native-async-storage/async-storage';
import NetInfo from '@react-native-community/netinfo';
import authReducer, { completeTwoFactor, login } from '@/features/auth/store/authSlice';
import * as authApi from '@/api/endpoint/auth.api';
import * as client from '@/api/client';
import * as LocalAuthentication from 'expo-local-authentication';
import { User } from '@/models/index';
import { beforeEach, describe, expect, it } from '@jest/globals';
import { jest } from '@jest/globals';
jest.mock('@api/endpoint/auth.api', () => ({
  ...jest.requireActual('@api/endpoint/auth.api'),
  login: jest.fn(),
  verifyTwoFactorLogin: jest.fn(),
  logoutRequest: jest.fn(),
}));
jest.mock('@api/client');
jest.mock('expo-secure-store', () => ({ isAvailableAsync: async () => false }));
jest.mock('expo-crypto', () => ({ getRandomBytes: (n: number) => Uint8Array.from({ length: n }, (_, i) => (i * 5 + 1) % 256) }));

const mockedLogin = authApi.login as jest.Mock;
const mockedVerify = authApi.verifyTwoFactorLogin as jest.Mock;
const mockedSetToken = client.setAuthToken as jest.Mock;
const mockedFetch = NetInfo.fetch as jest.Mock;

const user: User = { id: 'u1', fullName: 'Thandi', email: 'thandi@firm.co.za', role: 'CA' };
const store = () => configureStore({ reducer: { auth: authReducer } });
const online = () => mockedFetch.mockResolvedValue({ isConnected: true, isInternetReachable: true });
const offline = () => mockedFetch.mockResolvedValue({ isConnected: false, isInternetReachable: false });

beforeEach(async () => {
  await AsyncStorage.clear();
  jest.clearAllMocks();
  online();
});

describe('login sequence without a connection', () => {
  it('signs in offline after one earlier online sign-in, restoring the saved token', async () => {
    mockedLogin.mockResolvedValueOnce({ token: 'server-token', user });
    await store().dispatch(login({ email: user.email, password: 'Secret#123' }));

    offline();
    const s = store();
    await s.dispatch(login({ email: user.email, password: 'Secret#123' }));

    expect(mockedLogin).toHaveBeenCalledTimes(1); // no network attempt while offline
    expect(s.getState().auth.user).toEqual(user);
    expect(mockedSetToken).toHaveBeenLastCalledWith('server-token');
  });

  it('also falls back when the device looks online but the server cannot be reached', async () => {
    mockedLogin.mockResolvedValueOnce({ token: 't1', user });
    await store().dispatch(login({ email: user.email, password: 'Secret#123' }));

    mockedLogin.mockRejectedValueOnce(Object.assign(new Error('Network Error'), { isAxiosError: true, response: undefined }));
    const s = store();
    await s.dispatch(login({ email: user.email, password: 'Secret#123' }));
    expect(s.getState().auth.user).toEqual(user);
  });

  it('rejects a wrong password offline and a never-seen account', async () => {
    mockedLogin.mockResolvedValueOnce({ token: 't1', user });
    await store().dispatch(login({ email: user.email, password: 'Secret#123' }));
    offline();

    const wrong = store();
    await wrong.dispatch(login({ email: user.email, password: 'bad' }));
    expect(wrong.getState().auth.user).toBeNull();
    expect(wrong.getState().auth.error).toMatch(/incorrect/i);

    const stranger = store();
    await stranger.dispatch(login({ email: 'new@firm.co.za', password: 'x' }));
    expect(stranger.getState().auth.error).toMatch(/first time/i);
  });

  it('does not use the saved sign-in when the server answered "wrong password"', async () => {
    mockedLogin.mockResolvedValueOnce({ token: 't1', user });
    await store().dispatch(login({ email: user.email, password: 'Secret#123' }));

    mockedLogin.mockRejectedValueOnce(
      Object.assign(new Error('422'), { isAxiosError: true, response: { status: 422, data: { message: 'These credentials do not match our records.' } } }),
    );
    const s = store();
    await s.dispatch(login({ email: user.email, password: 'Secret#123' }));
    expect(s.getState().auth.user).toBeNull();
    expect(s.getState().auth.error).toMatch(/do not match/);
  });

  it('with two-factor on, online sign-in returns a challenge and NO token until the server accepts the code', async () => {
    mockedLogin.mockResolvedValueOnce({ twoFactorRequired: true, challengeToken: 'chal-1', method: 'totp' });
    const s = store();
    await s.dispatch(login({ email: user.email, password: 'Secret#123' }));

    expect(s.getState().auth.status).toBe('needs-2fa');
    expect(s.getState().auth.challenge).toEqual({ token: 'chal-1', method: 'totp', maskedPhone: undefined });
    expect(s.getState().auth.user).toBeNull();
    expect(mockedSetToken).not.toHaveBeenCalled();

    mockedVerify.mockResolvedValueOnce({ token: 'real-token', user: { ...user, twoFactorMethod: 'totp' } });
    await s.dispatch(completeTwoFactor({ code: '123456' }));
    expect(mockedVerify).toHaveBeenCalledWith('chal-1', '123456');
    expect(mockedSetToken).toHaveBeenCalledWith('real-token');
    expect(s.getState().auth.user?.twoFactorMethod).toBe('totp');
  });

  it('a wrong code keeps the challenge open and signs nobody in', async () => {
    mockedLogin.mockResolvedValueOnce({ twoFactorRequired: true, challengeToken: 'chal-2', method: 'sms', maskedPhone: '082••••567' });
    const s = store();
    await s.dispatch(login({ email: user.email, password: 'Secret#123' }));
    mockedVerify.mockRejectedValueOnce(
      Object.assign(new Error('422'), { isAxiosError: true, response: { status: 422, data: { message: 'The verification code is invalid or has expired.' } } }),
    );
    await s.dispatch(completeTwoFactor({ code: '000000' }));
    expect(s.getState().auth.user).toBeNull();
    expect(s.getState().auth.challenge).not.toBeNull();
  });

  it('offline sign-in for a two-factor account needs the phone\'s own fingerprint / PIN', async () => {
    const withTfa = { ...user, twoFactorMethod: 'totp' as const };
    mockedLogin.mockResolvedValueOnce({ twoFactorRequired: true, challengeToken: 'c', method: 'totp' });
    const first = store();
    await first.dispatch(login({ email: user.email, password: 'Secret#123' }));
    mockedVerify.mockResolvedValueOnce({ token: 't1', user: withTfa });
    await first.dispatch(completeTwoFactor({ code: '123456' }));

    offline();
    const ok = store();
    await ok.dispatch(login({ email: user.email, password: 'Secret#123' }));
    expect(ok.getState().auth.user).toEqual(withTfa);
    expect(LocalAuthentication.authenticateAsync).toHaveBeenCalled();

    (LocalAuthentication.authenticateAsync as jest.Mock).mockResolvedValueOnce({ success: false });
    const denied = store();
    await denied.dispatch(login({ email: user.email, password: 'Secret#123' }));
    expect(denied.getState().auth.user).toBeNull();
    expect(denied.getState().auth.error).toMatch(/Phone verification/);
  });

  it('refuses offline sign-in for a two-factor account on a phone with no screen lock', async () => {
    const withTfa = { ...user, twoFactorMethod: 'sms' as const };
    mockedLogin.mockResolvedValueOnce({ token: 't1', user: withTfa });
    await store().dispatch(login({ email: user.email, password: 'Secret#123' }));

    offline();
    (LocalAuthentication.getEnrolledLevelAsync as jest.Mock).mockResolvedValueOnce(LocalAuthentication.SecurityLevel.NONE);
    const s = store();
    await s.dispatch(login({ email: user.email, password: 'Secret#123' }));
    expect(s.getState().auth.user).toBeNull();
    expect(s.getState().auth.error).toMatch(/screen lock/);
  });
});
