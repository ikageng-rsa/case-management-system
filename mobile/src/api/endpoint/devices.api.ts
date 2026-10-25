import { apiClient } from '@/api/client';

export interface DeviceRegistration {
    token: string;
    tokenType: 'fcm' | 'apns' | 'expo';
    platform: 'android' | 'ios' | 'web';
    deviceName?: string;
}

/** Registers this phone's push token so the BACKEND can send it alerts. */
export async function registerDevice(payload: DeviceRegistration): Promise<{ id: string }> {
    const { data } = await apiClient.post<{ id: string }>('/devices', payload);
    return data;
}

export async function unregisterDevice(id: string): Promise<void> {
    await apiClient.delete(`/devices/${id}`);
}
