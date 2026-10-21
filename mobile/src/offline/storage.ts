import AsyncStorage from '@react-native-async-storage/async-storage';

const ROOT = 'legalcms_offline_v1';

export const userPrefix = (userId: string): string => `${ROOT}:${userId}:`;
export const cacheKey = (userId: string, name: string): string => `${userPrefix(userId)}cache:${name}`;
export const outboxKey = (userId: string): string => `${userPrefix(userId)}outbox`;
export const metaKey = (userId: string): string => `${userPrefix(userId)}meta`;

export async function readJson<T>(key: string): Promise<T | null> {
    try {
        const raw = await AsyncStorage.getItem(key);
        return raw ? (JSON.parse(raw) as T) : null;
    } catch {
        return null;
    }
}

export async function writeJson(key: string, value: unknown): Promise<void> {
    await AsyncStorage.setItem(key, JSON.stringify(value));
}

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
