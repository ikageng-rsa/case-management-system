import NetInfo from '@react-native-community/netinfo';
import { isAxiosError } from 'axios';

/** NetInfo says there is no network, or a network with no internet behind it. */
export async function isDeviceOffline(): Promise<boolean> {
    try {
        const state = await NetInfo.fetch();
        return state.isConnected === false || state.isInternetReachable === false;
    } catch {
        return false;
    }
}

/** The request never got an answer (no signal, DNS failure, timeout) — as opposed to the server saying no. */
export function isNetworkError(error: unknown): boolean {
    return isAxiosError(error) && !error.response;
}

/** Worth retrying later: no answer, or the server is overloaded/unavailable. */
export function isTransientError(error: unknown): boolean {
    if (!isAxiosError(error)) {
        return false;
    }
    if (!error.response) {
        return true;
    }
    const status = error.response.status;
    return status >= 500 || status === 408 || status === 429;
}
