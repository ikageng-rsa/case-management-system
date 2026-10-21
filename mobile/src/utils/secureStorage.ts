import AsyncStorage from "@react-native-async-storage/async-storage";
import * as SecureStore from 'expo-secure-store';

/**
 * Keychain (iOS) / Keystore (Android) storage for secrets. Fails back to AsyncStorage only where the
 * platform has no secure store (web preview), so the app still runs there.
 */

async function avaliable(): Promise<boolean>{
    try{
        return await SecureStore.isAvailableAsync();
    }catch{
        return false;
    }
}


export async function secureGet(key: string): Promise<string | null>{
    return (await avaliable()) ? SecureStore.getItemAsync(key) : AsyncStorage.getItem(key);
}

export async function secureSet(key: string, value: string): Promise<void>{
    if(await avaliable()){
        await SecureStore.setItemAsync(key,value);
    }else{
        await AsyncStorage.setItem(key,value);
    }
}

export async function secureDelete(key: string): Promise<void>{
    if(await avaliable()){
        await SecureStore.deleteItemAsync(key);
    }else{
        await AsyncStorage.removeItem(key);
    }
}