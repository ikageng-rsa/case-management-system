import { includedGet, Included, indexIncluded, relOne, Resource } from '../jsonapi';
import { ActivityType, Narration } from '@/models/index';

export function mapActivityType(resource: Resource): ActivityType {
    const a = resource.attributes ?? {};
    return {
        id: String(resource.id),
        code: a.code ?? '',
        name: a.name ?? a.code ?? '',
        measure: a.measure ?? 'minutes',
        increment: Number(a.increment) > 0 ? Number(a.increment) : 1,
        requiresCourt: Boolean(a.requires_court),
    };
}

export function mapNarration(resource: Resource, included: Included, caseId: string): Narration {
    const a = resource.attributes ?? {};
    const type = includedGet(included, relOne(resource, 'activityType'));
    const typeRef = relOne(resource, 'activityType');
    const author = relOne(resource, 'author');
    const activity = type ? mapActivityType(type) : undefined;
    const quantity = Number(a.quantity ?? 0);
    const billableUnits = Number(a.billable_units ?? 0);
    return {
        id: String(resource.id),
        caseId,
        authorId: author ? String(author.id) : '',
        activityType: activity?.code ?? '',
        activityTypeId: typeRef ? String(typeRef.id) : undefined,
        activityName: activity?.name,
        measure: activity?.measure,
        description: a.body ?? '',
        quantity,
        billableUnits,
        // Time totals only count activities measured in minutes.
        timeSpentMinutes: activity?.measure === 'minutes' ? quantity : 0,
        billable: billableUnits > 0,
        documentIds: [],
        occurredAt: a.occurred_at ?? undefined,
        createdAt: a.created_at ?? a.occurred_at ?? new Date().toISOString(),
    };
}

export function mapNarrationList(resources: Resource[], included: Resource[] | undefined, caseId: string): Narration[] {
    const index = indexIncluded(included);
    return resources.map(r => mapNarration(r, index, caseId));
}

/** Short human text for an amount: "1h 30m", "12 pages", "113 km", "3 items". */
export function formatQuantity(quantity: number, measure?: string): string {
    const q = Math.max(0, quantity || 0);
    switch (measure) {
        case 'minutes': {
            const h = Math.floor(q / 60);
            const m = q % 60;
            return h && m ? `${h}h ${m}m` : h ? `${h}h` : `${m}m`;
        }
        case 'page':
            return `${q} page${q === 1 ? '' : 's'}`;
        case 'kilometre':
            return `${q} km`;
        case 'item':
            return `${q} item${q === 1 ? '' : 's'}`;
        default:
            return String(q);
    }
}

export const MEASURE_LABEL: Record<string, string> = { minutes: 'Minutes', page: 'Pages', kilometre: 'Kilometres', item: 'Items' };
