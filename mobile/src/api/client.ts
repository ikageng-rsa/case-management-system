import axios from 'axios';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { Platform } from 'react-native';

// Pulled from .env via react-native-config in a real build; hardcoded fallback for the starter.
const API_BASE_URL = process.env.EXPO_PUBLIC_API_BASE_URL ?? (Platform.OS === 'web' ? 'http://localhost:8000/api' : process.env.EXPO_PUBLIC_MOBILE_API_BASE_URL);
const API_TIMEOUT_MS = Number(process.env.EXPO_PUBLIC_API_TIMEOUT_MS ?? 15000);
const AUTH_TOKEN_KEY = 'legalcms_auth_token';

export const apiClient = axios.create({
  baseURL: API_BASE_URL,
  timeout: API_TIMEOUT_MS,
  headers: {
    'Content-Type': 'application/json',
  },
});
console.log('platform: ',Platform.OS);
if(__DEV__){
  console.log(`[api] base URL = ${API_BASE_URL}`);

  apiClient.interceptors.request.use(config =>{
    console.log(`[api] -> ${config.method?.toUpperCase()} ${apiClient.getUri(config)}`);
    return config;
  });

  apiClient.interceptors.response.use(
    response => response,
    error =>{
      const where = error.config ? `${error.config.method?.toUpperCase()} ${apiClient.getUri(error.config)}`: '';
      console.log(`[api] x ${where} -> ${error.response?.status ?? error.message}`);
      return Promise.reject(error);
    },
  );

  if(!process.env.EXPO_PUBLIC_API_BASE_URL){
    console.log(`[api] EXPO_PUBLIC_API_BASE_URL is NOT set -> using the placeholder URL. Is .env in the project root? Restart with: npx expo start -c`);

  }else if(process.env.NODE_ENV !== 'test'){
    //Connectivity self-test at app start: GET {base}/health (public , no token)
    apiClient
      .get('/health')
      .then(res =>{
        const who = res.headers?.['x-mock-api'] ? 'the mock API':'a server that is NOT the mock API';
        console.log(`[api] health check OK - answered by ${who}:`, JSON.stringify(res.data))

      }).catch(()=>{}) //already logged by the interceptor above.
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
