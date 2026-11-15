import { includedGet, Included, indexIncluded, relMany, Resource } from '@/api/jsonapi';
import { ClientContact, ClientProfile } from '@/models/index';

export function mapClient(resource: Resource, included: Included): ClientProfile {
    const a = resource.attributes ?? {};
    const contacts: ClientContact[] = relMany(resource, 'contacts')
        .map(ref => includedGet(included, ref))
        .filter((r): r is Resource => Boolean(r))
        .map(r => ({
            id: String(r.id),
            kind: r.attributes?.kind ?? '',
            valueMasked: r.attributes?.value_masked ?? '',
            isPrimary: Boolean(r.attributes?.is_primary),
        }));
    return {
        id: String(resource.id),
        fullName: a.name ?? a.entity_name ?? '',
        clientType: a.client_type ?? 'individual',
        firstName: a.first_name ?? null,
        lastName: a.last_name ?? null,
        entityName: a.entity_name ?? null,
        identityType: a.identity_type ?? null,
        identifierMasked: a.identifier_masked ?? null,
        dateOfBirthMasked: a.date_of_birth_masked ?? null,
        mattersCount: typeof a.matters_count === 'number' ? a.matters_count : undefined,
        contacts: contacts.length ? contacts : undefined,
        can: resource.meta?.can,
    };
}

export function mapClientList(resources: Resource[], included: Resource[] | undefined): ClientProfile[] {
    const index = indexIncluded(included);
    return resources.map(r => mapClient(r, index));
}
