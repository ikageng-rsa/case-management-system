/**
 * Two-factor authentication, on-device:
 *  - TOTP ('totp'): standard 30-second, 6-digit codes compatible with Google
 *    Authenticator / Microsoft Authenticator — generated and verified with
 *    `otpauth` (a free, pure-JS library, no backend or paid service needed).
 *    The secret never leaves the device.
 *  - SMS OTP ('sms'): a 6-digit code delivered via the same SMS channel as
 *    overdue alerts (see smsService.ts) and verified via otpStore.ts.
 *
 * Settings are stored per-user in AsyncStorage rather than on the User model,
 * since there's no backend 2FA column to persist them against (mobile-only
 * change — see the note in @api/endpoints/auth.api.ts).
 */
import * as OTPAuth from 'otpauth';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { TwoFactorSettings, TwoFactorMethod } from '@/models/index';
import { issueOtp, verifyOtp } from '@/services/notifications/otpStore';

const SETTINGS_KEY_PREFIX = 'legalcms_2fa_';

function settingsKey(userId: string): string {
    return `${SETTINGS_KEY_PREFIX}${userId}`;
}

export async function getTwoFactorSettings(userId: string): Promise<TwoFactorSettings> {
    const raw = await AsyncStorage.getItem(settingsKey(userId));
    return raw ? (JSON.parse(raw) as TwoFactorSettings) : { method: 'none' };
}

async function saveSettings(userId: string, settings: TwoFactorSettings): Promise<void> {
    await AsyncStorage.setItem(settingsKey(userId), JSON.stringify(settings));
}

export function generateTotpSecret(): string {
    return new OTPAuth.Secret({ size: 20 }).base32;
}

/** otpauth:// URI — render this as a QR code for Google/Microsoft Authenticator to scan. */
export function buildTotpEnrollmentUri(secret: string, accountEmail: string): string {
    const totp = new OTPAuth.TOTP({
        issuer: 'LegalCMS',
        label: accountEmail,
        secret: OTPAuth.Secret.fromBase32(secret),
        digits: 6,
        period: 30,
    });
    return totp.toString();
}

export function verifyTotpCode(secret: string, code: string): boolean {
    const totp = new OTPAuth.TOTP({ secret: OTPAuth.Secret.fromBase32(secret), digits: 6, period: 30 });
    // window: 1 tolerates the code from the previous/next 30s step (clock drift).
    return totp.validate({ token: code.trim(), window: 1 }) !== null;
}

export async function enableTotp(userId: string, secret: string): Promise<void> {
    await saveSettings(userId, { method: 'totp', totpSecret: secret, enabledAt: new Date().toISOString() });
}

export async function enableSmsTwoFactor(userId: string, phoneNumber: string): Promise<void> {
    await saveSettings(userId, { method: 'sms', phoneNumber, enabledAt: new Date().toISOString() });
}

export async function disableTwoFactor(userId: string): Promise<void> {
    await saveSettings(userId, { method: 'none' });
}

export async function sendTwoFactorSmsChallenge(userId: string, phoneNumber: string) {
    return issueOtp('sms-2fa', userId, phoneNumber);
}

export async function verifyTwoFactorSmsChallenge(userId: string, code: string): Promise<boolean> {
    return verifyOtp('sms-2fa', userId, code);
}

/** Verifies whichever method is enabled for this user; 'none' always passes (no step-up required). */
export async function verifyTwoFactorChallenge(
    userId: string,
    settings: TwoFactorSettings,
    code: string,
): Promise<boolean> {
    if (settings.method === 'none') {
        return true;
    }
    if (settings.method === 'totp' && settings.totpSecret) {
        return verifyTotpCode(settings.totpSecret, code);
    }
    if (settings.method === 'sms') {
        return verifyTwoFactorSmsChallenge(userId, code);
    }
    return false;
}

export type { TwoFactorSettings, TwoFactorMethod };
