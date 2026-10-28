import { includedGet, Included, indexIncluded, relMany, relOne, Resource } from "../jsonapi";
import { Case, CaseAssignement, CasePriority, CaseStatus } from "@/models/index";

const STATUS: Record<string, CaseStatus> = {
    open: 'Open',
    in_process: 'In Progress',
    'in-process': 'In Progress',
    on_hold: 'On Hold',
    'on-hold': 'On Hold',
    closed: 'Closed',
    archived: 'Archived'
};

const titleCase = (s: string) => s.replace(/[_-]+/g, ' ').replaceAll(/\b\w/g, c => c.toUpperCase());
const day = (iso?: string | null) => (iso ? iso.slice(0, 10) : undefined);

export function mapStatus(raw?: string): CaseStatus {
    if (!raw) return 'Open';
    return STATUS[raw.toLowerCase()] ?? (titleCase(raw) as CaseStatus);
}

const DAY_MS = 86_400_000;

//The API has no priority field. Until it does, urgency is read from the deadlines so lists and the dashboard
//can still put the pression matters first:  prescription within 30 days or a missed deadline -> Urgent,
//a deadline within 7 days -> High, otherwise Medium.

export function derivePriority(nextDueAt?: string | null, prescribesAt?: string | null, now = Date.now()): CasePriority {
    const next = nextDueAt ? new Date(nextDueAt).getTime() : null;
    const presc = prescribesAt ? new Date(prescribesAt).getTime() : null;
    if (presc !== null && presc - now <= 30 * DAY_MS) return 'Urgent';
    if (next !== null && next < now) return 'Urgent';
    if (next !== null && next - now <= 7 * DAY_MS) return 'High'
    return 'Medium';
}

export function mapAssignment(resource: Resource, included: Included): CaseAssignement {
    const userRef = relOne(resource, 'User');
    const user = includedGet(included, userRef);
    return {
        id: String(resource.id),
        userId: userRef ? String(userRef.id) : '',
        userName: user?.attributes?.name,
        capacity: resource.attributes?.capacity ?? '',
        assignedAt: resource.attributes?.assigned_at,
    };
}

/**                                                                                                                       
 * One matter → the app's Case.                                                                                           
 * `meId` is used only for list rows, which carry `my_capacities` but not the full assignment list: a matter the          
 * user holds a capacity on is marked as theirs so the app's "my cases" scoping agrees with the server's.                 
 */
export function mapMatter(resource: Resource, included: Included, meId?: string | null): Case {
    const a = resource.attributes ?? {};
    const client = includedGet(included, relOne(resource, 'client'));
    const type = includedGet(included, relOne(resource, 'matterType'));
    const court = includedGet(included, relOne(resource, 'court'));
    const assignments = relMany(resource, 'assignments')
        .map(ref => includedGet(included, ref))
        .filter((r): r is Resource => Boolean(r))
        .map(r => mapAssignment(r, included));
    const myCapacities: string[] = Array.isArray(a.my_capacities) ? a.my_capacities : [];


    const assignedUserIds = assignments.length
        ? [...new Set(assignments.map(x => x.userId).filter(Boolean))]
        : meId && myCapacities.length
            ? [meId]
            : [];
    const lead = assignments.find(x => x.capacity === 'responsible') ?? assignments.find(x => x.capacity === 'supervising');


    return {
        id: String(resource.id),
        fileReference: a.reference ?? '',
        title: a.title ?? undefined,
        clientId: client ? String(client.id) : (relOne(resource, 'client')?.id ?? ''),
        clientName: client?.attributes?.name ?? '',
        matterType: type?.attributes?.name ?? '',
        matterTypeCode: type?.attributes?.code,
        practiceArea: type?.attributes?.name,
        status: mapStatus(a.status),
        rawStatus: a.status,
        priority: derivePriority(a.next_due_at, a.prescribes_at),
        leadAttorneyId: lead?.userId ?? '',
        assignedUserIds,
        assignments: assignments.length ? assignments : undefined,
        openedDate: day(a.instructed_at) ?? '',
        closedDate: day(a.closed_at),
        prescribesAt: a.prescribes_at ?? undefined,
        nextDueAt: a.next_due_at ?? undefined,
        myCapacities,
        documentsCount: typeof a.documents_count === 'number' ? a.documents_count : undefined,
        courtLabel: court?.attributes?.label ?? court?.attributes?.name,
        jurisdiction: court?.attributes?.label ?? court?.attributes?.name,
        can: resource.meta?.can,
    };
}


export function mapMatterList(resources: Resource[], included: Resource[] | undefined, meId?: string | null): Case[] {
    const index = indexIncluded(included);
    return resources.map(r => mapMatter(r, index, meId));
}

