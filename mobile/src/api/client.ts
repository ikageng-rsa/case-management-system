import axios from 'axios';
import AsyncStorage from '@react-native-async-storage/async-storage';

// Pulled from .env via react-native-config in a real build; hardcoded fallback for the starter.
const API_BASE_URL = process.env.EXPO_PUBLIC_API_BASE_URL || 'https://legalcms-dev.co.za/api';
const API_TIMEOUT_MS = Number(process.env.EXPO_PUBLIC_API_TIMEOUT_MS ?? 15000);
const AUTH_TOKEN_KEY = 'legalcms_auth_token';

export const apiClient = axios.create({
  baseURL: API_BASE_URL,
  timeout: API_TIMEOUT_MS,
  headers: {
    'Content-Type': 'application/json',
  },
});

//Dev-only diagnostics. open the Metro terminal (or the browser web) to see the
//exact URL every request goes to and what came back - the fastest way to spot a wrong

if(__DEV__){
  console.log(`[api] base URL = ${API_BASE_URL}`);

  apiClient.interceptors.request.use(config =>{
    console.log(`[api] -> ${config.method?.toUpperCase()} ${apiClient.getUri(config)}`);
    return config;
  });
  apiClient.interceptors.response.use(
    response => response,
    error => {
      const where = error.config ? `${error.config.method?.toUpperCase()} ${apiClient.getUri(error.config)}` : '';
      console.log(`[api] x ${where} -> ${error.response?.status ?? error.message}`);
      return Promise.reject(error)
    }
  );

  if(!process.env.EXPO_PUBLIC_API_BASE_URL){
    console.log('[api] EXPO_PUBLIC_API_BASE_URL is NOT set -> using the placeholder URL. Is .env in the project root? Restart with: npx expo start -c');
  }else{
    //Connectivity self-test at app start: GET {base}/health (public, no token).
    apiClient
      .get('/health')
      .then(res => {
        const who  = res.headers?.['x-api'] ? 'the API' : 'a server that is not the api';
        console.log(`[api] health check OK - answered by ${who}:`, JSON.stringify(res.data));
      })
      .catch(() => {})
  }
}

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

export function getAuthToken(): Promise<string | null> {
  return  AsyncStorage.getItem(AUTH_TOKEN_KEY);
}
