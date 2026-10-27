import AsyncStorage from '@react-native-async-storage/async-storage';
import { openString, sealString } from '@/utils/encryption';
import { encGetJson, encSetJson } from '@/utils/encryptedStorage';
import { bytesToBase64, base64ToBytes } from '@/utils/base64';
import { setActiveUserId } from '@/offline/context';
import { readJson, writeJson, cacheKey } from '@/offline/storage';
import { beforeEach, describe, expect, it } from '@jest/globals';
import { jest } from '@jest/globals';                            

describe('base64', () => {
  it('round-trips every length', () => {
    for (let n = 0; n < 40; n++) {
      const bytes = Uint8Array.from({ length: n }, (_, i) => (i * 37 + n) % 256);
      expect(Array.from(base64ToBytes(bytesToBase64(bytes)))).toEqual(Array.from(bytes));
    }
  });
});

describe('encryption at rest', () => {
  it('round-trips text and never stores it readable', async () => {
    const sealed = await sealString('Mokoena v Road Accident Fund');
    expect(sealed.startsWith('enc1:')).toBe(true);
    expect(sealed).not.toContain('Mokoena');
    expect(await openString(sealed)).toBe('Mokoena v Road Accident Fund');
  });

  it('uses a fresh nonce each time', async () => {
    expect(await sealString('same')).not.toBe(await sealString('same'));
  });

  it('detects tampering', async () => {
    const sealed = await sealString('confidential');
    const flipped = sealed.slice(0, -4) + (sealed.endsWith('AAAA') ? 'BBBB' : 'AAAA');
    expect(await openString(flipped)).toBeNull();
  });

  it('still reads data written by an older, unencrypted version', async () => {
    expect(await openString('{"legacy":true}')).toBe('{"legacy":true}');
  });

  it('the offline cache and outbox are ciphertext in AsyncStorage', async () => {
    await AsyncStorage.clear();
    setActiveUserId('u1');
    await writeJson(cacheKey('u1', 'cases'), [{ id: 'c1', clientName: 'Sensitive Client' }]);
    const raw = await AsyncStorage.getItem(cacheKey('u1', 'cases'));
    expect(raw).not.toContain('Sensitive Client');
    expect(await readJson(cacheKey('u1', 'cases'))).toEqual([{ id: 'c1', clientName: 'Sensitive Client' }]);
  });

  it('json helper returns null for a missing key', async () => {
    expect(await encGetJson('nope')).toBeNull();
    await encSetJson('k', { a: 1 });
    expect(await encGetJson('k')).toEqual({ a: 1 });
  });
});
