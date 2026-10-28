export type TwoFactorMethod = 'none' | 'totp' | 'sms';

/**
 * Stored on-device only (AsyncStorage, keyed by user id) — never sent to the
 * backend as-is, since this mobile app has no server-side 2FA endpoint of its
 * own to verify against. See @features/auth/services/twoFactorService.ts.
 */
export interface TwoFactorChallenge {
  token: string;
  method: Exclude<TwoFactorMethod, 'none'>;
  maskedPhone?: string;
  //Seconds the code stays valid (the backend's `expires_in)
  expiresIn?: number;
}
