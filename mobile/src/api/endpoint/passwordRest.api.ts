import { apiClient } from '../client';

/* Forgot password. NOT in the v1 guide yet: the screens are only reachable when the backend adds these routes
 * and the app is built with EXPO_PUBLIC_API_FEATURES=passwordReset. The BACKEND generates and delivers the code.
 *   POST /auth/password-reset            { email }
 *   POST /auth/password-reset/verify     { email, code }               -> { resetToken }
 *   POST /auth/password-reset/confirm    { resetToken, newPassword }   */
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
