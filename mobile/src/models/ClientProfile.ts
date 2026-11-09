import { ClientContact } from './ClientContact';

/**
 * A client as the v1 API returns it. Identity numbers and contact details arrive masked; the real value is
 * revealed per field (POST /clients/{id}/reveal), which the backend logs. Never log or persist a revealed value;
 * route any debug output through popiaLogger (@utils/popiaLogger).
 */
export interface ClientProfile {
  id: string;
  /** Display name (person's full name, or the entity name). */
  fullName: string;
  clientType: 'individual' | 'entity' | string;
  firstName?: string | null;
  lastName?: string | null;
  entityName?: string | null;
  identityType?: string | null;
  identifierMasked?: string | null;
  dateOfBirthMasked?: string | null;
  mattersCount?: number;
  contacts?: ClientContact[];
  can?: { view_contacts?: boolean; reveal?: boolean };
}
