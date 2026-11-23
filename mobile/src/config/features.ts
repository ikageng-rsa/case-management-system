/**
 * Which parts of the app talk to API endpoints that exist. The v1 API guide covers sign-in (with code resend and
 * sign-out), notifications and push devices, clients (with reveal), matters, assignments, parties, narrations,
 * documents (list / download / upload), the diary, the matter audit trail, users and lookups. Those are always on.
 *
 * Still not in the guide, so off until the backend documents them (turn one on with no code change:
 * EXPO_PUBLIC_API_FEATURES="passwordReset,notificationPreferences", comma separated):
 *   passwordReset            forgot-password flow (/auth/password-reset…)
 *   notificationPreferences  per-channel notification settings (/notifications/preferences)
 *
 * Always off, because v1 has no endpoint: creating / editing matters, editing a narration, editing clients,
 * users & roles administration.
 */
export type FeatureName = 'notifications' | 'documents' | 'signOut' | 'passwordReset' | 'notificationPreferences';

const DOCUMENTED: readonly FeatureName[] = ['notifications', 'documents', 'signOut'];

const enabled = new Set(
    (process.env.EXPO_PUBLIC_API_FEATURES ?? '')
        .split(',')
        .map(s => s.trim())
        .filter(Boolean),
);

export const hasFeature = (name: FeatureName): boolean => DOCUMENTED.includes(name) || enabled.has(name);

/** Thrown by write functions whose endpoint the backend does not offer yet. */
export class FeatureUnavailableError extends Error {
    constructor(what: string) {
        super(`${what} isn't available in the app yet. Please do this on the firm's web system.`);
        this.name = 'FeatureUnavailableError';
    }
}

/** What the signed-in user may do, from the permission names the API sends with `/me`. */
export const can = (permissions: string[] | undefined, name: string): boolean => Boolean(permissions?.includes(name));
