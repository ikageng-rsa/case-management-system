import { TwoFactorMethod } from './TwoFactor';

export type UserRole = 'Admin' | 'Director' | 'CA' | 'Secretary' | 'Messenger';

export interface User {
  id: string;
  fullName: string;
  email: string;
  /** Coarse role the screens use to decide which tabs to show. Derived from `roles` (see api/mappers/user.ts). */
  role: UserRole;
  /** The backend's own role names (e.g. "director"). */
  roles?: string[];
  /** What the signed-in user may do (e.g. "create_matters"). Only the user's own record carries these. */
  permissions?: string[];
  jobTitle?: string | null;
  twoFactorMethod?: TwoFactorMethod; // sign-in always asks for an emailed code
  phoneNumber?: string;
}
