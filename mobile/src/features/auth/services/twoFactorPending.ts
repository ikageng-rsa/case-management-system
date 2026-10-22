import AsyncStorage from '@react-native-async-storage/async-storage';

const KEY = 'legalcms_2fa_pending';

/**
 * Between "password accepted" and "2FA code accepted" a token already exists on the device (the SMS
 * challenge needs it). This flag marks that window so a force-quit during it can't be used to skip
 * the second factor: on the next start AuthProvider sees the flag and discards the token.
 */
export async function setTwoFactorPending(pending: boolean): Promise<void> {
    if (pending) {
        await AsyncStorage.setItem(KEY, '1');
    } else {
        await AsyncStorage.removeItem(KEY);
    }
}

export async function isTwoFactorPending(): Promise<boolean> {
    return (await AsyncStorage.getItem(KEY)) === '1';
}
