import { includedGet, Included, indexIncluded, relOne, Resource } from '@/api/jsonapi';
import { Darzation, DarzationPriority } from '@/models/index';

const ALL_ROLES: Darzation['visibleToRoles'] = ['Admin', 'Director', 'CA', 'Secretary', 'Messenger'];

const pad = (n: number) => String(n).padStart(2, '0');

/** The calendar day of a moment on the phone's own clock ("2026-11-14"). */
export function localDay(iso: string): string {
    const d = new Date(iso);
    return Number.isNaN(d.getTime()) ? iso.slice(0, 10) : `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

/**
 * The API has no priority on a diary entry. Urgency comes from where it stands: overdue → Urgent, due now → High,
 * otherwise Medium; a finished entry is Low.
 */
function priorityOf(isOverdue: boolean, isDue: boolean, done: boolean): DarzationPriority {
    if (done) return 'Low';
    if (isOverdue) return 'Urgent';
    return isDue ? 'High' : 'Medium';
}

/**
 * One diary entry → the app's Darzation.
 * `caseId` is passed in for the per-matter routes (create, complete, edit, reminder), whose answers do not repeat the
 * matter; the cross-matter list names it in `relationships.matter` instead.
 */
export function mapDiaryEntry(resource: Resource, included: Included, caseId?: string): Darzation {
    const a = resource.attributes ?? {};
    const matterRef = relOne(resource, 'matter');
    const matter = includedGet(included, matterRef);
    const assignee = relOne(resource, 'assignee');
    const assigneeUser = includedGet(included, assignee);
    const completedBy = relOne(resource, 'completedBy');
    const completedByUser = includedGet(included, completedBy);

    const body: string = a.body ?? '';
    const dueAt: string = a.due_at ?? '';
    const done = a.status === 'completed';
    const overdue = Boolean(a.is_overdue);
    const isDue = Boolean(a.is_due);
    const day = dueAt ? localDay(dueAt) : '';
    const assigneeId = assignee ? String(assignee.id) : '';

    return {
        id: String(resource.id),
        caseId: matterRef ? String(matterRef.id) : (caseId ?? ''),
        ownerId: assigneeId,
        title: body,
        nextAction: body,
        nextActionDate: day,
        dueDate: day,
        priority: priorityOf(overdue, isDue, done),
        status: done ? 'Done' : 'Pending',
        steps: [],
        assignedUserIds: assigneeId ? [assigneeId] : [],
        overdue: !done && overdue,
        visibleToRoles: ALL_ROLES,
        body,
        dueAt,
        isDue,
        assigneeId: assigneeId || undefined,
        assigneeName: assigneeUser?.attributes?.name,
        remindBeforeMinutes: typeof a.remind_before_minutes === 'number' ? a.remind_before_minutes : null,
        reminderLabel: a.reminder_label ?? undefined,
        completedAt: a.completed_at ?? null,
        completedById: completedBy ? String(completedBy.id) : null,
        completedByName: completedByUser?.attributes?.name,
        narrationId: a.narration_id ? String(a.narration_id) : null,
        matterReference: matter?.attributes?.reference,
        matterTitle: matter?.attributes?.title,
    };
}

export function mapDiaryList(resources: Resource[], included: Resource[] | undefined, caseId?: string): Darzation[] {
    const index = indexIncluded(included);
    return resources.map(r => mapDiaryEntry(r, index, caseId));
}

/** An id the phone made up for an entry added offline; the server never knows it. */
export const isLocalDiaryId = (id: string): boolean => id.startsWith('tmp_');
