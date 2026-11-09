import { Case, Darzation } from '@/models/index';
import { computeOverdue } from '@/models/Darzation';

/**                                                                                                          
 * The v1 API has no diary resource, but every matter carries its dates: `next_due_at` (next deadline) and   
 * `prescribes_at` (the claim prescribes). The Diary tab is read from those, so deadlines are visible and the
 * on-device reminders work without extra endpoints. Items are read-only and ids are `due:<matterId>` /      
 * `prescribes:<matterId>`.                                                                                  
 */

export const DUE_PREFIX = 'due:';
export const PRESCRIBES_PREFIX = 'prescribes:';

const ALL_ROLES: Darzation['visibleToRoles'] = new Set(['Admin', 'Director', 'CA', 'Secretary', 'Messager']);

function item(c: Case, kind: 'due' | 'prescribes', iso: string, now: Date): Darzation {
    const dueDate = iso.slice(0, 10);
    const label = c.title || c.clientName || c.fileReference;
    const title = kind === 'due' ? `Deadline - ${label}` : `Prscription - ${label}`;

    return {
        id: `${kind === 'due' ? DUE_PREFIX : PRESCRIBES_PREFIX}${c.id}`,
        caseId: c.id,
        ownerId: c.leadAttorneyId,
        title,
        nextAction: kind === 'due' ? `Next deadline on ${c.fileReference}` : `Claim prescribes on ${c.fileReference}`,
        nextActionDate: dueDate,
        dueDate,
        priority: kind === 'prescribes'? 'Urgent':'Medium',
        status: 'Pending',
        steps: [],
        assignedUserIds: c.assignedUserIds ?? [],
        overdue: computeOverdue(dueDate, 'Pending',now) && dueDate < now.toISOString().slice(0,10),
        visibleToRoles: ALL_ROLES
    }
}

//Open matters only: a closed matter has no deadlines to chase
export function deriveDiary(cases: Case[],now = new Date()): Darzation[]{
    const out: Darzation[] = [];
    for(const c of cases){
        if(c.status === 'Closed' || c.status === 'Archived' || c.closedDate){
            continue;
        }
        if(c.nextDueAt) out.push(item(c,'due',c.nextDueAt, now));
        if(c.prescribesAt) out.push(item(c, 'prescribes', c.prescribesAt, now));
    }
    return out.sort((a,b) => a.dueDate.localeCompare(b.dueDate));
}

export const isDerivedDiaryId = (id: string): boolean => id.startsWith(DUE_PREFIX) || id.startsWith(PRESCRIBES_PREFIX)