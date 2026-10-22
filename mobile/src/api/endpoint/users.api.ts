import { apiClient } from "../client";
import { User } from "@/models/index";
import {CacheNames} from '@/offline/cache';
import {cachedList} from '@/offline/repository';

export async function fetchUsers() : Promise<User[]>{
    return cachedList<User>({
        name: CacheNames.users,
        request: async () => (await apiClient.get<User[]>('/users')).data,
    });
}