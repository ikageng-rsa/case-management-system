/**
 * Sign-in without a connection.
 *
 * After every successful ONLINE sign-in the device keeps, in the secure store (Keychain/Keystore):
 *   - the user's profile and the session token the server gave us, and
 *   - a salted PBKDF2-SHA256 hash of the password (never the password itself).
 * Offline, the typed password is hashed the same way and compared; a match restores that profile and
 * token, so changes made offline can be synced as soon as the phone is back online.
 *
 * Guard rails: only accounts that previously signed in on THIS device qualify; the saved sign-in expires
 * after OFFLINE_LOGIN_MAX_DAYS (default 14) without an online sign-in; five wrong passwords lock offline
 * sign-in for five minutes; a password reset removes the saved credential.
 */
import AsyncStorage from '@react-native-async-storage/async-storage';
import { encGetJson, encSetJson } from '@/utils/encryptedStorage';
import { getRandomBytes } from 'expo-crypto';
import { pbkdf2Async } from '@noble/hashes/pbkdf2.js';
import { sha256 } from '@noble/hashes/sha2.js';
import { bytesToHex, hexToBytes } from '@noble/hashes/utils.js';
import { User } from '@/models/index';
import { secureDelete, secureGet, secureSet } from '@/utils/secureStorage';

const CRED_PREFIX = 'legalcms_offline_cred_';
const ATTEMPTS_PREFIX = 'legalcms_offline_attempts_';
const SESSION_USER_KEY = 'legalcms_session_user';
const ITERATIONS = 60_000;
const MAX_ATTEMPTS = 5;
const LOCK_MS = 5 * 60 * 1000;

export const OFFLINE_LOGIN_MAX_DAYS = Number(process.env.EXPO_PUBLIC_OFFLINE_LOGIN_DAYS ?? 14);

interface StoredCredential {
    v: 1;
    email: string;
    user: User;
    token: string;
    salt: string; // hex
    iterations: number;
    hash: string; // hex
    savedAt: string; // ISO — last ONLINE sign-in
}

const normalise = (email: string): string => email.trim().toLowerCase();

/** Keys must be [A-Za-z0-9._-] for the secure store, so use a hash of the e-mail. */
function idFor(email: string): string {
    return bytesToHex(sha256(new TextEncoder().encode(normalise(email)))).slice(0, 40);
}

async function derive(password: string, salt: Uint8Array, iterations: number): Promise<string> {
    const out = await pbkdf2Async(sha256, new TextEncoder().encode(password), salt, { c: iterations, dkLen: 32 });
    return bytesToHex(out);
}

function sameHex(a: string, b: string): boolean {
    if (a.length !== b.length) {
        return false;
    }
    let diff = 0;
    for (let i = 0; i < a.length; i++) {
        diff |= (a.codePointAt(i) ??0) ^ (b.codePointAt(i) ??0);
    }
    return diff === 0;
}

export async function saveOfflineCredential(params: { email: string; password: string; user: User; token: string }): Promise<void> {
    const salt = getRandomBytes(16);
    const record: StoredCredential = {
        v: 1,
        email: normalise(params.email),
        user: params.user,
        token: params.token,
        salt: bytesToHex(salt),
        iterations: ITERATIONS,
        hash: await derive(params.password, salt, ITERATIONS),
        savedAt: new Date().toISOString(),
    };
    await secureSet(CRED_PREFIX + idFor(params.email), JSON.stringify(record));
    await AsyncStorage.removeItem(ATTEMPTS_PREFIX + idFor(params.email));
}

/**
 * After a normal sign-out the saved session token is removed (the server revokes it too), so a stolen phone
 * can't use it. Offline sign-in still works; unsent changes then wait for an online sign-in before syncing.
 */
export async function stripSavedToken(email: string): Promise<void> {
    const key = CRED_PREFIX + idFor(email);
    const raw = await secureGet(key);
    if (!raw) {
        return;
    }
    try {
        await secureSet(key, JSON.stringify({ ...(JSON.parse(raw) as StoredCredential), token: '' }));
    } catch {
        await secureDelete(key);
    }
}

export async function forgetOfflineCredential(email: string): Promise<void> {
    await secureDelete(CRED_PREFIX + idFor(email));
}

export type OfflineLoginResult =
    | { ok: true; user: User; token: string }
    | { ok: false; reason: 'no-credential' | 'wrong-password' | 'expired' | 'locked'; message: string };

export async function verifyOfflineLogin(email: string, password: string, now = Date.now()): Promise<OfflineLoginResult> {
    const id = idFor(email);
    const attemptsRaw = await AsyncStorage.getItem(ATTEMPTS_PREFIX + id);
    const attempts: { count: number; lockedUntil?: number } = attemptsRaw ? JSON.parse(attemptsRaw) : { count: 0 };
    if (attempts.lockedUntil && attempts.lockedUntil > now) {
        const minutes = Math.ceil((attempts.lockedUntil - now) / 60000);
        return { ok: false, reason: 'locked', message: `Too many wrong passwords. Try again in ${minutes} minute${minutes === 1 ? '' : 's'}.` };
    }

    const raw = await secureGet(CRED_PREFIX + id);
    let record: StoredCredential | null = null;
    try {
        record = raw ? (JSON.parse(raw) as StoredCredential) : null;
    } catch {
        record = null;
    }
    if (!record) {
        return {
            ok: false,
            reason: 'no-credential',
            message: "You're offline, and this account hasn't signed in on this device before. Connect to the internet to sign in the first time.",
        };
    }

    const ageDays = (now - new Date(record.savedAt).getTime()) / 86_400_000;
    if (ageDays > OFFLINE_LOGIN_MAX_DAYS) {
        return {
            ok: false,
            reason: 'expired',
            message: `Offline sign-in expires after ${OFFLINE_LOGIN_MAX_DAYS} days. Connect to the internet and sign in again.`,
        };
    }

    const hash = await derive(password, hexToBytes(record.salt), record.iterations);
    if (!sameHex(hash, record.hash)) {
        const count = attempts.count + 1;
        await AsyncStorage.setItem(
            ATTEMPTS_PREFIX + id,
            JSON.stringify(count >= MAX_ATTEMPTS ? { count: 0, lockedUntil: now + LOCK_MS } : { count }),
        );
        return { ok: false, reason: 'wrong-password', message: 'Incorrect email or password.' };
    }

    await AsyncStorage.removeItem(ATTEMPTS_PREFIX + id);
    return { ok: true, user: record.user, token: record.token };
}

/* --- the profile of whoever is signed in, so a cold start without internet can restore the session --- */

export async function saveSessionUser(user: User): Promise<void> {
    await encSetJson(SESSION_USER_KEY, user);
}

export async function getSessionUser(): Promise<User | null> {
    return encGetJson<User>(SESSION_USER_KEY);
}

export async function clearSessionUser(): Promise<void> {
    await AsyncStorage.removeItem(SESSION_USER_KEY);
}
