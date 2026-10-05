import { User } from '@/models/index';

/**
 * Built-in demo account so the app can be signed into without a backend.
 *
 * Enabled automatically in development builds (__DEV__). In a release build it
 * stays OFF unless EXPO_PUBLIC_ENABLE_DEMO_LOGIN=true is set at build time, so a
 * shipped app never accepts a hardcoded password by accident.
 */
export const isDemoLoginEnabled: boolean =
  __DEV__ || process.env.EXPO_PUBLIC_ENABLE_DEMO_LOGIN === 'true';

export const DEMO_CREDENTIALS = {
  email: process.env.EXPO_PUBLIC_DEMO_EMAIL ?? 'demo@legalcms.co.za',
  password: process.env.EXPO_PUBLIC_DEMO_PASSWORD ?? 'Demo@1234',
} as const;

// Admin so every tab (Cases, Diary, Billing) and the Users & Roles screen are reachable.
export const DEMO_USER: User = {
  id: 'demo-user',
  fullName: 'Demo Admin',
  email: DEMO_CREDENTIALS.email,
  role: 'Admin',
};

// Stored in place of a real JWT so a cold start can restore the demo session.
export const DEMO_TOKEN = 'demo-token';

export function isDemoLogin(email: string, password: string): boolean {
  return (
    isDemoLoginEnabled &&
    email.trim().toLowerCase() === DEMO_CREDENTIALS.email.toLowerCase() &&
    password === DEMO_CREDENTIALS.password
  );
}
