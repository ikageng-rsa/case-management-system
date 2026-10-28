import { apiClient } from "../client";
import { Document, indexIncluded, Resource } from "../jsonapi";
import { mapClient, mapClientList } from "../mappers/client";
import { fetchAllPage } from "../pagination";
import { ClientProfile } from "@/models/index";
import { CacheNames } from "@/offline/cache";
import { cachedList } from "@/offline/repository";

//Clients the user may see. The masked list is cached (it holds no real identity or contact values)
export async function fetchClients(search?: string): Promise<{ clients: ClientProfile[]; offline: boolean }> {
    const q = search?.trim();
    let fromServer = false;
    const clients = await cachedList<ClientProfile>(
        {
            name: CacheNames.clients,
            cacheable: !q,
            request: async () => {
                const { data, included } = await fetchAllPage('/clients', q ? { q } : {})
                fromServer = true;
                return mapClientList(data, included);
            },
            filter: q ? rows => rows.filter(c => c.fullName.toLowerCase().includes(q.toLowerCase())) : undefined,
        }
    );
    return {clients, offline: !fromServer}
}

export async function fetchClient(id: string): Promise<ClientProfile>{
    const { data: doc} = await apiClient.get<Document<Resource>>(`/clients/${id}`);
    return mapClient(doc.data as Resource, indexIncluded(doc.included));
}


//Asks the server for the real value of one masked field : `identifier` or `contact:{contactId}`. The server records
//who looked. The value is returned to the caller only - never cached, logged or put in Redux.

export async function revealClientField(clientId: string, target: 'identifier' | `contact:${string}`): Promise<string>{
    const {data: doc} = await apiClient.post<Document<Resource>>(`/clients/${clientId}/reveal`,{target});
    const value = (doc.data as Resource).attributes?.value;
    if(typeof value !== 'string'){
        throw new Error('The server did not return the value.');
    }
    return value;
}