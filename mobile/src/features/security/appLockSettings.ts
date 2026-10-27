import { secureGet, secureSet } from "@/utils/secureStorage";


const KEY = 'legalcms_app_lock';

//On by default; the user can switch it off in More -> Security. Only takes effect if the phone has a screen lock
export async function getAppLockEnabled(): Promise<boolean>{
    return (await secureGet(KEY)) !== 'off';
}

export async function setAppLockEnabled(enabled: boolean): Promise<void>{
    await secureSet(KEY, enabled ? 'on': 'off');
}
