/**
 * Encryption at rest for everything the app keeps on the phone that came from the firm's data
 * (offline cache, unsent changes, attachment list, saved profile).
 *
 * XChaCha20-Poly1305 (authenticated: tampering is detected) with a random 24-byte nonce per write.
 * The 256-bit key is generated on first use and lives ONLY in the Keychain/Keystore (secureStorage), so the
 * AsyncStorage files — which can be read from a rooted phone or a device backup — are ciphertext.
 * Format: "enc1:" + base64(nonce ‖ ciphertext).
 */
import { xchacha20poly1305 } from '@noble/ciphers/chacha.js';
import { bytesToHex, hexToBytes } from '@noble/hashes/utils.js';
import { getRandomBytes } from 'expo-crypto';
import { base64ToBytes, bytesToBase64 } from '@/utils/base64';
import { secureGet, secureSet } from '@/utils/secureStorage';

const KEY_NAME = 'legalcms_storage_key_v1';
const PREFIX = 'enc1:';
const NONCE_BYTES = 24;

let keyPromise: Promise<Uint8Array> | null = null;

function getKey(): Promise<Uint8Array> {
    if (!keyPromise) {
        keyPromise = (async () => {
            const existing = await secureGet(KEY_NAME);
            if (existing && existing.length === 64) {
                return hexToBytes(existing);
            }
            const fresh = getRandomBytes(32);
            await secureSet(KEY_NAME, bytesToHex(fresh));
            return fresh;
        })().catch(error => {
            keyPromise = null; // let the next call try again
            throw error;
        });
    }
    return keyPromise;
}

export const isSealed = (value: string): boolean => value.startsWith(PREFIX);

export async function sealString(plain: string): Promise<string> {
    const nonce = getRandomBytes(NONCE_BYTES);
    const cipher = xchacha20poly1305(await getKey(), nonce);
    const sealed = cipher.encrypt(new TextEncoder().encode(plain));
    const joined = new Uint8Array(NONCE_BYTES + sealed.length);
    joined.set(nonce, 0);
    joined.set(sealed, NONCE_BYTES);
    return PREFIX + bytesToBase64(joined);
}

/**
 * Returns the plain text, or null when the value was tampered with or the key is gone (e.g. app data restored
 * onto another phone) — callers treat that as "nothing stored". A value without the prefix is data written
 * by an older version of the app and is returned as-is (it is re-encrypted the next time it is saved).
 */
export async function openString(stored: string): Promise<string | null> {
    if (!isSealed(stored)) {
        return stored;
    }
    try {
        const joined = base64ToBytes(stored.slice(PREFIX.length));
        const cipher = xchacha20poly1305(await getKey(), joined.slice(0, NONCE_BYTES));
        return new TextDecoder().decode(cipher.decrypt(joined.slice(NONCE_BYTES)));
    } catch {
        return null;
    }
}

/** Forget the key (and with it, everything sealed with it). */
export function resetEncryptionKeyCache(): void {
    keyPromise = null;
}
