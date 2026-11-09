type LocalAuth = typeof import('expo-local-authentication');
/**                                                                                             
 * Loaded lazily and defensively: if the native module isn't in the installed build (or fails to
 * initialise), the app must still start. Without it, device auth is simply "unavailable".      
 */
function load(): LocalAuth | null {
    try {
        //eslint-disable-next-line 
        return require('expo-local-authentication') as LocalAuth;
    } catch {
        return null;
    }
}


//the phone has a screen lock /biometrics enrolled, so it can vouch that the holder is the owner
export async function deviceAuthAvailable(): Promise<boolean> {
    const auth = load();
    if (!auth) {
        return false;
    }
    try {
        // "Enrolled level" counts a PIN / pattern / password as well as fingerprint or face. Checking only for 
        // biometric hardware (hasHardwareAsync + isEnrolledAsync) wrongly reported "unavailable" on phones that
        // are protected by a PIN alone, which left the app-lock switch dead.                                   
        return (await auth.getEnrolledLevelAsync()) !== auth.SecurityLevel.NONE;

    }catch{
        return false;
    }
}

//Fingerprint / face, failing back to the phone's PIN, pattern or password.
export async function requireDeviceAuth(reason: string): Promise<boolean> {
    const auth = load();
    if(!auth || !(await deviceAuthAvailable())){
        return false;
    }
    try{
        const result  = await auth.authenticateAsync({promptMessage: reason, cancelLabel: 'Cancel'});
        return result.success;
    }catch{
        return false;
    }
}

