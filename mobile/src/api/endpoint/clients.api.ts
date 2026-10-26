import { apiClient } from '@/api/client';
import { fetchCases } from '@/api/endpoint/cases.api';
import { isNetworkError } from '@/offline/connectivity';
import { ClientContactType, ClientProfile, PopiaConsentType, RiskRating } from '@/models/index';

/** Only what the person is allowed to change here; the backend checks roles again. */
export interface ClientChanges {
    contactNumber?: string;
    email?: string;
    address?: string;
    notes?: string;
    /** Sent to the backend once and stored there only as a hash plus the last four digits. */
    idNumber?: string;
    ficaVerified?: boolean;
    riskRating?: RiskRating;
}

/** Clients known from the cases already on this device (names only), for when there is no connection. */
async function clientsFromCases(): Promise<ClientProfile[]> {
    const cases = await fetchCases();
    const byClient = new Map<string, ClientProfile>();
    for (const c of cases) {
        if (!c.clientId || byClient.has(c.clientId)) {
            continue;
        }
        const mine = cases.filter(x => x.clientId === c.clientId);
        byClient.set(c.clientId, {
            id: c.clientId,
            fullName: c.clientName,
            contactNumber: '',
            email: '',
            ficaVerified: false,
            riskRating: 'Low',
            caseIds: mine.map(x => x.id),
            openCases: mine.filter(x => x.status === 'Open' || x.status === 'In Progress').length,
        });
    }
    return [...byClient.values()].sort((a, b) => a.fullName.localeCompare(b.fullName));
}

export async function fetchClients(): Promise<{ clients: ClientProfile[]; offline: boolean }> {
    try {
        const { data } = await apiClient.get<ClientProfile[]>('/clients');
        return { clients: data, offline: false };
    } catch (error) {
        if (!isNetworkError(error)) {
            throw error;
        }
        return { clients: await clientsFromCases(), offline: true };
    }
}

export async function fetchClient(id: string): Promise<{ client: ClientProfile; offline: boolean }> {
    try {
        const { data } = await apiClient.get<ClientProfile>(`/clients/${id}`);
        return { client: data, offline: false };
    } catch (error) {
        if (!isNetworkError(error)) {
            throw error;
        }
        const known = (await clientsFromCases()).find(c => c.id === id);
        if (!known) {
            throw error;
        }
        return { client: known, offline: true };
    }
}

export async function updateClient(id: string, changes: ClientChanges): Promise<ClientProfile> {
    return (await apiClient.patch<ClientProfile>(`/clients/${id}`, changes)).data;
}

export async function addClientContact(
    id: string,
    contact: { type: ClientContactType; value: string; isPrimary?: boolean },
): Promise<ClientProfile> {
    return (await apiClient.post<ClientProfile>(`/clients/${id}/contacts`, contact)).data;
}

export async function recordClientConsent(
    id: string,
    consent: { consentType: PopiaConsentType; granted: boolean; consentVersion?: string },
): Promise<ClientProfile> {
    return (await apiClient.post<ClientProfile>(`/clients/${id}/consents`, consent)).data;
}
