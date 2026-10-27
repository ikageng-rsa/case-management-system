/**
 * Which user's offline data is "Active". Everything on disk (cache, outbox) is 
 * namespaced by user id, so two people sharing a device never see each other's data.
 * Kept in sync with the Redux auth state by store.ts (synchronously, so screens that
 * fetch on mount already see the right user)
 */

let activeUserId: string | null = null;

export function setActiveUserId(id: string | null): void{
    activeUserId = id;
}

export function getActiveUserId(): string|null{
    return activeUserId;
}