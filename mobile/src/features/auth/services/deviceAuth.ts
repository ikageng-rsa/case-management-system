import * as LocalAuthentication from 'expo-local-authentication';

//the phone has a screen lock /biometrics enrolled, so it can vouch that the holder is the owner
export async function deviceAuthAvailable(): Promise<boolean>{
    try{
        return (await LocalAuthentication.hasHardwareAsync()) && (await LocalAuthentication.isEnrolledAsync());
    }catch{
        return false;
    }
}

//Fingerprint / face, failing back to the phone's PIN, pattern or password.
export async function requireDeviceAuth(reason: string): Promise<boolean>{
    if(!(await deviceAuthAvailable)){
        return false;
    }

    try{
        const result = await LocalAuthentication.authenticateAsync({promptMessage: reason, cancelLabel: 'Cancel'});
        return result.success;
    }catch{
        return false;
    }
}

