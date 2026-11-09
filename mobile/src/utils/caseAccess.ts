/**
 * Who sees what. With the v1 API the SERVER decides: `/matters` only returns matters the signed-in user may open,
 * and narrations come from those matters. The helpers below therefore pass the lists through for a signed-in user
 * (and keep the old call sites working) instead of re-deriving access on the phone, where it could disagree with the
 * server. Per-action rights come from each matter's `can` (see `canWorkOn`).
 */
import { Case, Darzation, Narration, User } from '@/models/index';

type Who = Pick<User, 'id' | 'role'> | null | undefined;

export function visibleCasesFor(cases: Case[], user: Who): Case[] {
    return user ? cases : [];
}

export function canSeeCase(_item: Case, user: Who): boolean {
    return Boolean(user);
}

export function visibleDarzationsFor(darzations: Darzation[], _cases: Case[], user: Who): Darzation[] {
    return user ? darzations : [];
}

export function visibleNarrationsFor(narrations: Narration[], _cases: Case[], user: Who): Narration[] {
    return user ? narrations : [];
}

/** May the signed-in user record work (narrations) on this matter? Uses the server's answer. */
export function canWorkOn(item: Pick<Case, 'can'> | null | undefined): boolean {
    return item?.can?.work !== false;
}

/** May the signed-in user manage who is assigned to this matter? */
export function canAssignOn(item: Pick<Case, 'can'> | null | undefined): boolean {
    return item?.can?.assign === true;
}
