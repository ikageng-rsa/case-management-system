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
