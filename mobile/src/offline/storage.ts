import AsyncStorage from '@react-native-async-storage/async-storage';
import { encGetJson, encSetJson } from '@/utils/encryptedStorage';

const ROOT = 'legalcms_offline_v1';

export const userPrefix = (userId: string): string => `${ROOT}:${userId}:`;
export const cacheKey = (userId: string, name: string): string => `${userPrefix(userId)}cache:${name}`;
export const outboxKey = (userId: string): string => `${userPrefix(userId)}outbox`;
export const metaKey = (userId: string): string => `${userPrefix(userId)}meta`;

/** Everything under this prefix is encrypted at rest (see utils/encryption.ts). */
export const readJson = <T>(key: string): Promise<T | null> => encGetJson<T>(key);

export const writeJson = (key: string, value: unknown): Promise<void> => encSetJson(key, value);

export async function listUserKeys(userId: string): Promise<string[]> {
    const all = await AsyncStorage.getAllKeys();
    const prefix = userPrefix(userId);
    return all.filter(k => k.startsWith(prefix));
}

/**
 * Cache and outbox are read-modify-write, and several screens write at once.
 * Running every write through one promise chain keeps them from clobbering each other.
 */
let chain: Promise<unknown> = Promise.resolve();
export function serial<T>(task: () => Promise<T>): Promise<T> {
    const run = chain.then(task, task);
    chain = run.catch(() => undefined);
    return run;
}
