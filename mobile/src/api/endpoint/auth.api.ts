import { apiClient } from '@/api/client';
import { TwoFactorChallenge, User } from '@/models/index';

export interface LoginSuccess {
  token: string;
  user: User;
}

/** Account has two-factor on: no token yet, only a challenge to answer. */
export interface LoginChallenge {
  twoFactorRequired: true;
  challengeToken: string;
  method: TwoFactorChallenge['method'];
  maskedPhone?: string;
}

export type LoginResponse = LoginSuccess | LoginChallenge;

export const isChallenge = (r: LoginResponse): r is LoginChallenge => 'twoFactorRequired' in r && r.twoFactorRequired === true;

export async function login(email: string, password: string): Promise<LoginResponse> {
  const { data } = await apiClient.post<LoginResponse>('/auth/login', { email, password });
  return data;
}

/** Step 2 of a two-factor sign-in: the backend checks the code and only then issues the session token. */
export async function verifyTwoFactorLogin(challengeToken: string, code: string): Promise<LoginSuccess> {
  const { data } = await apiClient.post<LoginSuccess>('/auth/2fa/verify', { challengeToken, code });
  return data;
}

/** SMS method only: asks the backend to send a fresh code. */
export async function resendTwoFactorCode(challengeToken: string): Promise<void> {
  await apiClient.post('/auth/2fa/resend', { challengeToken });
}

export async function fetchCurrentUser(): Promise<User> {
  const { data } = await apiClient.get<User>('/auth/me');
  return data;
}

export async function updateProfile(payload: Partial<Pick<User, 'fullName' | 'phoneNumber'>>): Promise<User> {
  const { data } = await apiClient.patch<User>('/auth/me', payload);
  return data;
}

/** Revokes the session token on the server. */
export async function logoutRequest(): Promise<void> {
  await apiClient.post('/auth/logout');
}

/* Forgot password: the BACKEND generates and delivers the code (e-mail/SMS) and checks it; the app only relays it.
 *   POST /auth/password-reset            { email }                      -> same answer whether or not the account exists
 *   POST /auth/password-reset/verify     { email, code }                -> { resetToken }
 *   POST /auth/password-reset/confirm    { resetToken, newPassword }    -> signs the user out everywhere */
export async function requestPasswordResetCode(email: string): Promise<void> {
  await apiClient.post('/auth/password-reset', { email });
}

export async function verifyPasswordResetCode(email: string, code: string): Promise<{ resetToken: string }> {
  const { data } = await apiClient.post<{ resetToken: string }>('/auth/password-reset/verify', { email, code });
  return data;
}

export async function confirmPasswordReset(resetToken: string, newPassword: string): Promise<void> {
  await apiClient.post('/auth/password-reset/confirm', { resetToken, newPassword });
}

/* Two-factor settings (signed in). The backend owns the secret; it is shown once at setup so it can be scanned. */
export async function startTotpSetup(): Promise<{ secret: string; otpauthUri: string }> {
  const { data } = await apiClient.post<{ secret: string; otpauthUri: string }>('/auth/2fa/totp/setup');
  return data;
}

export async function enableTotp(code: string): Promise<User> {
  const { data } = await apiClient.post<User>('/auth/2fa/totp/enable', { code });
  return data;
}

export async function startSmsSetup(phoneNumber: string): Promise<void> {
  await apiClient.post('/auth/2fa/sms/setup', { phoneNumber });
}

export async function enableSms(code: string): Promise<User> {
  const { data } = await apiClient.post<User>('/auth/2fa/sms/enable', { code });
  return data;
}

export async function disableTwoFactor(password: string): Promise<User> {
  const { data } = await apiClient.post<User>('/auth/2fa/disable', { password });
  return data;
}
