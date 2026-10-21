import AsyncStorage from "@react-native-async-storage/async-storage";
import {getActiveUserId} from './context';
import {listUserKeys} from './storage';
import {resetSyncStatus} from './syncStatus';

//Remove this user's cache and unsent changes from the device (shared-device / sign-out hygiene)
export async function clearOfflineData(userId = getActiveUserId()): Promise<void>{
    if(!userId){
        return;
    }
    await AsyncStorage.multiRemove(await listUserKeys(userId));
    resetSyncStatus();
}