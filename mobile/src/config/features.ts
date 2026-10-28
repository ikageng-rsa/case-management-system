/**
 * Which parts of the app talk to API endpoints that exist yet. The v1 API guide covers sign-in, matters,
 * assignments, narrations, clients (with reveal), users and lookups. Anything else is switched off until the
 * backend documents it, so the app never shows a button that would fail.
 *
 * Turn one on without code changes: EXPO_PUBLIC_API_FEATURES="notifications,documents" (comma separated).
 *   notifications  GET /notifications, /notifications/unread-count, POST …/read, /devices   (push inbox + badges)
 *   documents      matter documents list / upload / download
 *   passwordReset  forgot-password flow (/auth/password-reset…)
 *   signOut        POST /auth/sign-out (revoke the token on the server)
 *
 * Always off for now, because v1 has no endpoint: creating / editing matters, editing a narration, editing
 * clients, writing diary items, users & roles administration. The diary tab is read from the matters' dates.
 */
export type FeatureName = 'notifications' | 'documents' | 'passwordReset' | 'signOut';

const enabled = new Set(
    (process.env.EXPO_PUBLIC_API_FEATURES ?? '')
        .split(',')
        .map(s => s.trim())
        .filter(Boolean),
);

export const hasFeature = (name: FeatureName): boolean => enabled.has(name);

/** Thrown by write functions whose endpoint the backend does not offer yet. */
export class FeatureUnavailableError extends Error {
    constructor(what: string) {
        super(`${what} isn't available in the app yet. Please do this on the firm's web system.`);
        this.name = 'FeatureUnavailableError';
    }
}

/** What the signed-in user may do, from the permission names the API sends with `/me`. */
export const can = (permissions: string[] | undefined, name: string): boolean => Boolean(permissions?.includes(name));
