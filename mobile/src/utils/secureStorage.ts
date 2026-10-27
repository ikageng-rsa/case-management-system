import AsyncStorage from '@react-native-async-storage/async-storage';
import * as SecureStore from 'expo-secure-store';

/**
 * Keychain (iOS) / Keystore (Android) storage for secrets. Items are readable only while the phone is
 * unlocked and are tied to THIS device (never restored from a backup onto another one).
 * Falls back to AsyncStorage only where the platform has no secure store (web preview).
 */
async function available(): Promise<boolean> {
    try {
        return await SecureStore.isAvailableAsync();
    } catch {
        return false;
    }
}

export async function secureGet(key: string): Promise<string | null> {
    return (await available()) ? SecureStore.getItemAsync(key) : AsyncStorage.getItem(key);
}

export async function secureSet(key: string, value: string): Promise<void> {
    if (await available()) {
        await SecureStore.setItemAsync(key, value, { keychainAccessible: SecureStore.WHEN_UNLOCKED_THIS_DEVICE_ONLY });
    } else {
        await AsyncStorage.setItem(key, value);
    }
}

export async function secureDelete(key: string): Promise<void> {
    if (await available()) {
        await SecureStore.deleteItemAsync(key);
    } else {
        await AsyncStorage.removeItem(key);
    }
}
