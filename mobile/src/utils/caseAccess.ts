/**
 * Single source of truth for the "who sees what" rule behind the Director-vs-everyone-else
 * dashboard/list scoping: a Director (or Admin) sees every case/diary/narration in the firm;
 * every other role only sees records tied to a case they are currently assigned to or have
 * worked on before. Screens call these helpers instead of re-deriving the rule themselves.
 */
import { Case, Darzation, Narration, User } from '@/models/index';
import { hasGlobalVisibility } from '@/constants/roles';

/** Cases a user may see: all of them for Director/Admin, otherwise only ones they're assigned to. */
export function visibleCasesFor(cases: Case[], user: Pick<User, 'id' | 'role'> | null | undefined): Case[] {
    if (!user) {
        return [];
    }
    if (hasGlobalVisibility(user.role)) {
        return cases;
    }
    return cases.filter(c => c.assignedUserIds?.includes(user.id));
}

export function canSeeCase(item: Case, user: Pick<User, 'id' | 'role'> | null | undefined): boolean {
    if (!user) {
        return false;
    }
    return hasGlobalVisibility(user.role) || item.assignedUserIds?.includes(user.id);
}

/** Diary items scoped to the cases a user may see (or to their own assignment on the item itself). */
export function visibleDarzationsFor(
    darzations: Darzation[],
    cases: Case[],
    user: Pick<User, 'id' | 'role'> | null | undefined,
): Darzation[] {
    if (!user) {
        return [];
    }
    if (hasGlobalVisibility(user.role)) {
        return darzations;
    }
    const visibleCaseIds = new Set(visibleCasesFor(cases, user).map(c => c.id));
    return darzations.filter(d => visibleCaseIds.has(d.caseId) || d.assignedUserIds?.includes(user.id) || d.ownerId === user.id);
}

/** Narrations scoped the same way — tied to a case the user can see. */
export function visibleNarrationsFor(
    narrations: Narration[],
    cases: Case[],
    user: Pick<User, 'id' | 'role'> | null | undefined,
): Narration[] {
    if (!user) {
        return [];
    }
    if (hasGlobalVisibility(user.role)) {
        return narrations;
    }
    const visibleCaseIds = new Set(visibleCasesFor(cases, user).map(c => c.id));
    return narrations.filter(n => visibleCaseIds.has(n.caseId) || n.authorId === user.id);
}
