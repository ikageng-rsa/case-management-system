export type UserRole = 'Admin' | 'Director' | 'CA' | 'Secretary' | 'Messenger';

export interface User {
  id: string;
  fullName: string;
  email: string;
  role: UserRole;
}
