export type UserRole = 'Admin' | 'Director' | 'CA' | 'Secretary' | 'Messenger';

export interface User {
  id: string;
  fullName: string;
  email: string;
  role: UserRole;
  phoneNumber?: string; // SA format (+27xxxxxxxxx or 0xxxxxxxxx) — used for SMS OTP and overdue SMS alerts
}
