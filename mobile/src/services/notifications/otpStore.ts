/**
 * Shared one-time-code store for flows that need a short-lived verification
 * code — forgot-password and SMS 2FA. Generates the code, delivers it over
 * SMS via smsService, and verifies it on-device.
 *
 * This exists because the backend doesn't (yet) expose code issue/verify
 * endpoints (out of scope here — mobile folder only); the matching
 * `@api/endpoints/auth.api.ts` calls are kept as the preferred path and are
 * tried first, so once the backend adds those two routes this on-device
 * fallback stops being used automatically. Codes are single-use, time-boxed,
 * and never logged.
 */
import AsyncStorage from '@react-native-async-storage/async-storage';
import { sendSms } from './smsService';

const STORE_KEY_PREFIX = 'legalcms_otp_';
const CODE_TTL_MS = 10 * 60 * 1000; // 10 minutes
const MAX_ATTEMPTS = 5;

interface StoredOtp {
    code: string;
    expiresAt: number;
    attempts: number;
}

function storeKey(purpose: string, identifier: string): string {
    return `${STORE_KEY_PREFIX}${purpose}_${identifier}`.toLowerCase();
}

function generateCode(length = 6): string {
    let code = '';
    for (let i = 0; i < length; i++) {
        code += Math.floor(Math.random() * 10).toString();
    }
    return code;
}

export interface IssueOtpResult {
    delivered: boolean;
    deliveryError?: string;
    /** Only populated when no SMS provider is configured, so the demo still works end-to-end. */
    devCode?: string;
}

export async function issueOtp(
    purpose: 'password-reset' | 'sms-2fa',
    identifier: string,
    phoneNumber: string | undefined,
): Promise<IssueOtpResult> {
    const code = generateCode();
    const record: StoredOtp = { code, expiresAt: Date.now() + CODE_TTL_MS, attempts: 0 };
    await AsyncStorage.setItem(storeKey(purpose, identifier), JSON.stringify(record));

    if (!phoneNumber) {
        return { delivered: false, deliveryError: 'No phone number on file', devCode: __DEV__ ? code : undefined };
    }

    const result = await sendSms(phoneNumber, `Your LegalCMS verification code is ${code}. It expires in 10 minutes.`);
    if (!result.ok) {
        // Still let a dev build see the code so the flow is testable without a live SMS provider.
        return { delivered: false, deliveryError: result.error, devCode: __DEV__ ? code : undefined };
    }
    return { delivered: true };
}

export async function verifyOtp(purpose: 'password-reset' | 'sms-2fa', identifier: string, code: string): Promise<boolean> {
    const key = storeKey(purpose, identifier);
    const raw = await AsyncStorage.getItem(key);
    if (!raw) {
        return false;
    }
    const record = JSON.parse(raw) as StoredOtp;
    if (Date.now() > record.expiresAt || record.attempts >= MAX_ATTEMPTS) {
        await AsyncStorage.removeItem(key);
        return false;
    }
    if (record.code !== code.trim()) {
        record.attempts += 1;
        await AsyncStorage.setItem(key, JSON.stringify(record));
        return false;
    }
    await AsyncStorage.removeItem(key); // single use
    return true;
}
