import { apiClient } from '@/api/client';
import { User } from '@/models/index';

export interface LoginResponse {
  token: string;
  user: User;
}

export async function login(email: string, password: string): Promise<LoginResponse> {
  const { data } = await apiClient.post<LoginResponse>('/auth/login', { email, password });
  return data;
}

export async function requestPasswordReset(email: string): Promise<void> {
  await apiClient.post('/auth/password-reset', { email });
}

export async function fetchCurrentUser(): Promise<User> {
  const { data } = await apiClient.get<User>('/auth/me');
  return data;
}

export async function updateProfile(payload: Partial<Pick<User, 'fullName' |  'email'>>): Promise<User>{
  const {data} = await apiClient.patch<User>('/auth/me', payload);
  return data;
}

/**
 * Forgot password -a 3-step flow (request code -> verify code => set new password)
 * , not a single-step emailed link. The backend doesn't yet expose these two extra endpoints 
 * (out of scope for this change - mobile-only)
 */

export async function requestPasswordResetCode(email: string): Promise<void>{
  await apiClient.post('/auth/password-reset',{email});
}

export async function verifyPasswordResetCode(email: string, code: string): Promise<{ resetToken: string}>{
  const {data} = await apiClient.post<{resetToken: string}>('/auth/password-reset/verify', {email,code});
  return data;
}

export async function confirmPasswordReset(resetToken: string, newPassword: string): Promise<void>{
  await apiClient.post('/auth/password-reset/confirm', {resetToken, newPassword})
}


/**
 * SMS OTP for 2FA (TOTP is handled entirely on-device - see twoFactorServices).
 * Same "backend should add this" note applies:
 *  post /auth/2fa/sms/send {}
 *  post /auth/2fa/sms/verify {code}
 */

export async function sendSmsOtp(): Promise<void>{
  await apiClient.post('/auth/2fa/sms/send', {});
}

export async function verifySmsOtp(code: string): Promise<{verified: boolean}>{
  const {data} = await apiClient.post<{verified: boolean}>('auth/2fa/sms/verify',{code});
  return data;
}
