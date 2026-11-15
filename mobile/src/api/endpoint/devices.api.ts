import { apiClient } from '@api/client';
import { Document, Resource } from '@api/jsonapi';

export interface DeviceRegistration {
    platform: 'ios' | 'android';
    /** The phone's push token, up to 512 characters. */
    pushToken: string;
}

export const MAX_PUSH_TOKEN_LENGTH = 512;

/**
 * Registers this phone's push token so the BACKEND can send it alerts. One phone per sign-in: calling it again
 * replaces the token (200), and signing out or changing the password removes it. The token is never sent back.
 */
export async function registerDevice(payload: DeviceRegistration): Promise<{ id: string }> {
    const { data: doc } = await apiClient.post<Document<Resource>>('/devices', {
        platform: payload.platform,
        push_token: payload.pushToken,
    });
    return { id: String((doc.data as Resource).id) };
}

/** The phone stops getting push messages but stays signed in (204). */
export async function unregisterDevice(id: string): Promise<void> {
    await apiClient.delete(`/devices/${id}`);
}
