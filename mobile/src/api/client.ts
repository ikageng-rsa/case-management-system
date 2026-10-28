import axios from 'axios';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { secureDelete, secureGet, secureSet } from '@/utils/secureStorage';

// EXPO_PUBLIC_* variables from .env are inlined at build time by Expo (restart with -c after editing .env).
// `||` not `??`: a blank `EXPO_PUBLIC_API_BASE_URL=` must fall back too — an empty baseURL makes the web build
// call its own dev server (http://localhost:8081/auth/login), which answers 404.
const API_BASE_URL = process.env.EXPO_PUBLIC_API_BASE_URL || 'https://legalcms-dev.contoso.co.za/api';
const API_TIMEOUT_MS = Number(process.env.EXPO_PUBLIC_API_TIMEOUT_MS ?? 15000);
const AUTH_TOKEN_KEY = 'legalcms_auth_token';

// The session token is a bearer credential: it lives in the Keychain/Keystore, never in AsyncStorage.
// Release builds refuse to talk to the firm's data over plain http.
if (!__DEV__ && !/^https:\/\//i.test(API_BASE_URL)) {
  throw new Error('EXPO_PUBLIC_API_BASE_URL must be an https:// address in a release build.');
}

export const apiClient = axios.create({
  baseURL: API_BASE_URL,
  timeout: API_TIMEOUT_MS,
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
});

// Dev-only diagnostics. Open the Metro terminal (or the browser console on web) to see the
// exact URL every request goes to and what came back — the fastest way to spot a wrong
// EXPO_PUBLIC_API_BASE_URL (.env not loaded, localhost on a phone, another server on the port...).
if (__DEV__) {
  console.log(`[api] base URL = ${API_BASE_URL}`);

  apiClient.interceptors.request.use(config => {
    console.log(`[api] -> ${config.method?.toUpperCase()} ${apiClient.getUri(config)}`);
    return config;
  });
  apiClient.interceptors.response.use(
    response => response,
    error => {
      const where = error.config ? `${error.config.method?.toUpperCase()} ${apiClient.getUri(error.config)}` : '';
      console.log(`[api] x ${where} -> ${error.response?.status ?? error.message}`);
      return Promise.reject(error);
    },
  );

  if (!process.env.EXPO_PUBLIC_API_BASE_URL) {
    console.log('[api] EXPO_PUBLIC_API_BASE_URL is NOT set -> using the placeholder URL. Is .env in the project root? Restart with: npx expo start -c');
  }

}

// In-memory copy so every request doesn't hit the keystore.
let cachedToken: string | null | undefined;

export async function getAuthToken(): Promise<string | null> {
  if (cachedToken === undefined) {
    cachedToken = await secureGet(AUTH_TOKEN_KEY);
    // A token left in AsyncStorage by an older version is discarded (the user signs in once more).
    await AsyncStorage.removeItem(AUTH_TOKEN_KEY).catch(() => { });
  }
  return cachedToken;
}

export async function setAuthToken(token: string): Promise<void> {
  cachedToken = token;
  await secureSet(AUTH_TOKEN_KEY, token);
}

export async function clearAuthToken(): Promise<void> {
  cachedToken = null;
  await secureDelete(AUTH_TOKEN_KEY);
}

let onUnauthorized: (() => void) | null = null;
/** The store registers this so a rejected session signs the user out (kept out of here to avoid a circular import). */
export function setUnauthorizedHandler(handler: (() => void) | null): void {
  onUnauthorized = handler;
}

apiClient.interceptors.request.use(async config => {
  const token = await getAuthToken();
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

apiClient.interceptors.response.use(
  response => response,
  async error => {
    // 401 from anywhere except the sign-in calls themselves means the server no longer accepts this session.
    if (error.response?.status === 401 && !String(error.config?.url ?? '').startsWith('/auth/login')) {
      await clearAuthToken();
      onUnauthorized?.();
    }
    throw error;
  },
);
