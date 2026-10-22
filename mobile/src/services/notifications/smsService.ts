/**
 * SMS delivery, abstracted behind one `sendSms()` call so the rest of the app
 * (overdue alerts, SMS OTP for 2FA/password reset) never needs to know which
 * provider is configured.
 *
 * SECURITY NOTE: sending SMS straight from a mobile client means any API key
 * bundled into the app is extractable from the binary. The safest long-term
 * home for this is a backend endpoint (`backend` provider below posts to the
 * existing API and lets the server hold the real credentials) — this file
 * supports that as the recommended option, with two genuinely free, keyless
 * or sandbox-only fallbacks for when no such endpoint exists yet:
 *
 *  - Textbelt ('textbelt'): the shared key "textbelt" sends 1 free SMS/day
 *    per IP with no signup — fine for demos/low volume. A paid key works too
 *    and is a straight drop-in (EXPO_PUBLIC_TEXTBELT_KEY).
 *  - Africa's Talking sandbox ('africastalking'): free sandbox app
 *    (username "sandbox") that only delivers to numbers you've registered
 *    as test numbers in the AT dashboard — safe for development, never a
 *    live/billed account.
 *
 * Configure via app config / .env (see .env.example):
 *   EXPO_PUBLIC_SMS_PROVIDER = backend | textbelt | africastalking | none (default: none)
 */
import { apiClient } from '@/api/client';

export type SmsProvider = 'backend' | 'textbelt' | 'africastalking' | 'none';

export interface SendSmsResult {
    ok: boolean;
    provider: SmsProvider;
    error?: string;
}

function getProvider(): SmsProvider {
    const configured = (process.env.EXPO_PUBLIC_SMS_PROVIDER ?? 'none').toLowerCase();
    if (configured === 'backend' || configured === 'textbelt' || configured === 'africastalking') {
        return configured;
    }
    return 'none';
}

export async function sendSms(toPhoneNumber: string, message: string): Promise<SendSmsResult> {
    const provider = getProvider();

    try {
        switch (provider) {
            case 'backend': {
                // Preferred: the server holds the real provider credentials.
                await apiClient.post('/notifications/sms', { to: toPhoneNumber, message });
                return { ok: true, provider };
            }
            case 'textbelt': {
                const key = process.env.EXPO_PUBLIC_TEXTBELT_KEY ?? 'textbelt';
                const res = await fetch('https://textbelt.com/text', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ phone: toPhoneNumber, message, key }),
                });
                const json = await res.json();
                return { ok: Boolean(json.success), provider, error: json.success ? undefined : json.error };
            }
            case 'africastalking': {
                const username = process.env.EXPO_PUBLIC_AT_USERNAME ?? 'sandbox';
                const apiKey = process.env.EXPO_PUBLIC_AT_API_KEY;
                if (!apiKey) {
                    return { ok: false, provider, error: 'EXPO_PUBLIC_AT_API_KEY is not set' };
                }
                const res = await fetch('https://api.sandbox.africastalking.com/version1/messaging', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        Accept: 'application/json',
                        apiKey,
                    },
                    body: new URLSearchParams({ username, to: toPhoneNumber, message }).toString(),
                });
                const json = await res.json();
                const recipient = json?.SMSMessageData?.Recipients?.[0];
                const ok = recipient?.status === 'Success';
                return { ok, provider, error: ok ? undefined : recipient?.status ?? 'Unknown error' };
            }
            case 'none':
            default:
                return { ok: false, provider: 'none', error: 'No SMS provider configured (EXPO_PUBLIC_SMS_PROVIDER)' };
        }
    } catch (error) {
        return { ok: false, provider, error: error instanceof Error ? error.message : 'SMS send failed' };
    }
}
