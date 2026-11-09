import { apiClient } from "../client";
import { User } from "@/models/index";
import {CacheNames} from '@/offline/cache';
import {cachedList} from '@/offline/repository';
import { fetchAllPage } from "../pagination";
import { mapUser } from "../mappers/user";
export async function fetchUsers() : Promise<User[]>{
    return cachedList<User>({
        name: CacheNames.users,
        request: async () => (await fetchAllPage('/users')).data.map(mapUser),
    });
}