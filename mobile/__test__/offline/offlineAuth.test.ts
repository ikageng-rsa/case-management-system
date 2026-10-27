import AsyncStorage from '@react-native-async-storage/async-storage';
import {
  forgetOfflineCredential,
  saveOfflineCredential,
  verifyOfflineLogin,
} from '@/features/auth/services/offlineAuth';
import { User } from '@/models/index';
import { beforeEach, describe, expect, it } from '@jest/globals';
import { jest } from '@jest/globals';
jest.mock('expo-secure-store', () => ({ isAvailableAsync: async () => false }));
jest.mock('expo-crypto', () => ({ getRandomBytes: (n: number) => Uint8Array.from({ length: n }, (_, i) => (i * 7 + 3) % 256) }));

const user: User = { id: 'u1', fullName: 'Thandi', email: 'thandi@firm.co.za', role: 'CA' };
const DAY = 86_400_000;

beforeEach(() => AsyncStorage.clear());

describe('offline sign-in', () => {
  it('accepts the right password (any e-mail casing) and returns the saved profile and token', async () => {
    await saveOfflineCredential({ email: user.email, password: 'Secret#123', user, token: 'tok-1' });
    const result = await verifyOfflineLogin('  Thandi@Firm.co.za ', 'Secret#123');
    expect(result).toEqual({ ok: true, user, token: 'tok-1' });
  });

  it('never stores the password itself', async () => {
    await saveOfflineCredential({ email: user.email, password: 'Secret#123', user, token: 'tok-1' });
    const dump = JSON.stringify(await AsyncStorage.multiGet(await AsyncStorage.getAllKeys()));
    expect(dump).not.toContain('Secret#123');
  });

  it('rejects a wrong password, and locks after five tries', async () => {
    await saveOfflineCredential({ email: user.email, password: 'Secret#123', user, token: 't' });
    for (let i = 0; i < 5; i++) {
      const r = await verifyOfflineLogin(user.email, 'nope');
      expect(r.ok).toBe(false);
    }
    const locked = await verifyOfflineLogin(user.email, 'Secret#123');
    expect(locked).toMatchObject({ ok: false, reason: 'locked' });
    // ...and the lock lifts after five minutes
    expect(await verifyOfflineLogin(user.email, 'Secret#123', Date.now() + 6 * 60_000)).toMatchObject({ ok: true });
  });

  it('refuses an account that never signed in on this device', async () => {
    expect(await verifyOfflineLogin('stranger@firm.co.za', 'x')).toMatchObject({ ok: false, reason: 'no-credential' });
  });

  it('expires after the allowed number of days without an online sign-in', async () => {
    await saveOfflineCredential({ email: user.email, password: 'Secret#123', user, token: 't' });
    expect(await verifyOfflineLogin(user.email, 'Secret#123', Date.now() + 20 * DAY)).toMatchObject({ ok: false, reason: 'expired' });
  });

  it('is removed by a password reset', async () => {
    await saveOfflineCredential({ email: user.email, password: 'Old#1234', user, token: 't' });
    await forgetOfflineCredential(user.email);
    expect(await verifyOfflineLogin(user.email, 'Old#1234')).toMatchObject({ ok: false, reason: 'no-credential' });
  });
});
