import axios from 'axios';
import AsyncStorage from '@react-native-async-storage/async-storage';

// Pulled from .env via react-native-config in a real build; hardcoded fallback for the starter.
const API_BASE_URL = 'https://legalcms-dev.co.za/api';
const API_TIMEOUT_MS = Number(process.env.EXPO_PUBLIC_API_TIMEOUT_MS ?? 15000);
const AUTH_TOKEN_KEY = 'legalcms_auth_token';

export const apiClient = axios.create({
  baseURL: API_BASE_URL,
  timeout: API_TIMEOUT_MS,
  headers: {
    'Content-Type': 'application/json',
  },
});

apiClient.interceptors.request.use(async config => {
  const token = await AsyncStorage.getItem(AUTH_TOKEN_KEY);
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

apiClient.interceptors.response.use(
  response => response,
  async error => {
    if (error.response?.status === 401) {
      await AsyncStorage.removeItem(AUTH_TOKEN_KEY);
      // Navigation reset to Auth stack is handled by AuthProvider listening
      // for this via an emitted event — kept out of the client to avoid a
      // circular dependency between api/ and app/navigation/.
    }
     throw error;
  },
);

export async function setAuthToken(token: string) {
  await AsyncStorage.setItem(AUTH_TOKEN_KEY, token);
}

export async function clearAuthToken() {
  await AsyncStorage.removeItem(AUTH_TOKEN_KEY);
}

export async function getAuthToken(): Promise<string | null> {
  return AsyncStorage.getItem(AUTH_TOKEN_KEY);
}
