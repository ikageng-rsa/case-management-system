import { Resource } from '../jsonapi';
import { User, UserRole } from '@/models/index';


//The backend names roles in lower case ("director"). The screens only need a coarse role to decide which tabs to
// show; what a user may actually do comes from `permissions` and from each matter's `can`, both enforced server-side.
// An unknown role gets the most limited role that still sees matters.

export function mapRole(roles: string[] | undefined): UserRole {
    const names = (roles ?? []).map(r => r.toLowerCase().replace(/[\s_-]+/g, ''));
    const has = (...candidates: string[]) => candidates.some(c => names.includes(c));
    if (has('admin', 'administrator', 'superadmin')) return 'Admin';
    if (has('director', 'partner')) return 'Director';
    if (has('attorney', 'candidateattorney', 'ca', 'associate', 'advocate')) return 'CA';
    if (has('messenger', 'runner')) return 'Messenger';
    return 'Secretary';
}

export function mapUser(resource: Resource): User {
    const a = resource.attributes ?? {};
    const roles: string[] | undefined = Array.isArray(a.roles) ? a.roles : undefined;
    return {
        id: String(resource.id),
        fullName: a.name ?? '',
        email: a.email ?? '',
        role: mapRole(roles),
        roles,
        permissions: Array.isArray(a.permissions) ? a.permissions : undefined,
        jobTitle: a.job_title ?? null,
        // Every sign-in is confirmed with a code the backend e-mails.
        twoFactorMethod: 'email',
    };
}
