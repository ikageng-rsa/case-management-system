export type TwoFactorMethod = 'none' | 'totp' | 'sms' | 'email';

/**
 * Two-factor authentication is enforced by the BACKEND: it holds the secret / sends the SMS and checks the code.
 * After a correct password the login call returns this challenge instead of a token; the app exchanges the
 * challenge token + code for the session token (POST /auth/2fa/verify).
 */
export interface TwoFactorChallenge {
  token: string;
  method: Exclude<TwoFactorMethod, 'none'>;
  maskedPhone?: string;
  /** Seconds the code stays valid (the backend's `expires_in`). */
  expiresIn?: number;
}
