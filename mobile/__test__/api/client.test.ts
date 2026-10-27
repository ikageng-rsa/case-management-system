import AsyncStorage from '@react-native-async-storage/async-storage';
import { setAuthToken, getAuthToken, clearAuthToken } from '@/api/client';
import { secureGet } from '@/utils/secureStorage';
import { beforeEach, describe, expect, it } from '@jest/globals';

describe('auth token storage', () => {
  beforeEach(async () => {
    await clearAuthToken();
  });

  it('returns null when no token has been stored', async () => {
    expect(await getAuthToken()).toBeNull();
  });

  it('persists and retrieves a token', async () => {
    await setAuthToken('abc-123');
    expect(await getAuthToken()).toBe('abc-123');
  });

  it('overwrites a previously stored token', async () => {
    await setAuthToken('first-token');
    await setAuthToken('second-token');
    expect(await getAuthToken()).toBe('second-token');
  });

  it('clears a stored token', async () => {
    await setAuthToken('to-be-cleared');
    await clearAuthToken();
    expect(await getAuthToken()).toBeNull();
  });

  it('keeps the token in the secure store, never in AsyncStorage', async () => {
    await setAuthToken('secret-token');
    expect(await secureGet('legalcms_auth_token')).toBe('secret-token');
    const keys = await AsyncStorage.getAllKeys();
    const values = await AsyncStorage.multiGet(keys);
    expect(JSON.stringify(values)).not.toContain('secret-token');
  });
});
