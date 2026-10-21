export type TwoFactorMethod = 'none' | 'totp' | 'sms';

/**
 * Stored on-device only (AsyncStorage, keyed by user id) — never sent to the
 * backend as-is, since this mobile app has no server-side 2FA endpoint of its
 * own to verify against. See @features/auth/services/twoFactorService.ts.
 */
export interface TwoFactorSettings {
  method: TwoFactorMethod;
  totpSecret?: string; // base32 secret, shown as a QR code for Google/Microsoft Authenticator
  phoneNumber?: string; // for SMS OTP
  enabledAt?: string;
}
