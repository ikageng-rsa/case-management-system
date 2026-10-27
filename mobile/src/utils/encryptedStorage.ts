import AsyncStorage from '@react-native-async-storage/async-storage';
import { openString, sealString } from '@/utils/encryption';

/** AsyncStorage with encryption at rest (see encryption.ts). Same shape as AsyncStorage's string API. */
export async function encGetItem(key: string): Promise<string | null> {
    const raw = await AsyncStorage.getItem(key);
    return raw === null ? null : openString(raw);
}

export async function encSetItem(key: string, value: string): Promise<void> {
    await AsyncStorage.setItem(key, await sealString(value));
}

export async function encGetJson<T>(key: string): Promise<T | null> {
    try {
        const text = await encGetItem(key);
        return text ? (JSON.parse(text) as T) : null;
    } catch {
        return null;
    }
}

export async function encSetJson(key: string, value: unknown): Promise<void> {
    await encSetItem(key, JSON.stringify(value));
}
