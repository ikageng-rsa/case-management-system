import { mapUser } from '@/api/mappers/user';
import { fetchAllPage } from '@/api/pagination';
import { User } from '@/models/index';
import { CacheNames } from '@/offline/cache';
import { cachedList } from '@/offline/repository';

/** Colleagues, for names on narrations and for assigning matters. Roles/permissions only come for the signed-in user. */
export async function fetchUsers(): Promise<User[]> {
  return cachedList<User>({
    name: CacheNames.users,
    request: async () => (await fetchAllPage('/users')).data.map(mapUser),
  });
}
