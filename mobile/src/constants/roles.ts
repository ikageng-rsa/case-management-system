import { UserRole } from '@/models/index';

export const ROLES: Record<UserRole, UserRole> = {
  Admin: 'Admin',
  Director: 'Director',
  CA: 'CA',
  Secretary: 'Secretary',
  Messenger: 'Messenger'
};

//Which roles can see which bottom tabs - keep this the single source of truth
//so navigation and screen-level guards never drift apart.
export const TAB_ACCESS: Record<string, UserRole[]> = {
  Cases: ['Admin', 'Director', 'CA', 'Secretary'],
  Diary: ['Admin', 'Director', 'CA', 'Secretary'],
  Documents: ['Admin', 'Director', 'CA', 'Secretary', 'Messenger'],
  Billing: ['Admin', 'Director', 'CA'],
  More: ['Admin', 'Director', 'CA', 'Secretary', 'Messenger'],
};


export function canAccessTab(tab: keyof typeof TAB_ACCESS, role: UserRole): boolean {
  return TAB_ACCESS[tab]?.includes(role) ?? false;
}

//Users & Roles administration - used by both the More menu and the route guards.
export function canAdministerUsers(role: UserRole): boolean {
  return role === ROLES.Admin || role === ROLES.Director;
}

//Director (and Admin) see every case/diary/narration system-wide; every other
//role only sees records tied to cases they assigned to or have worked on.
//This is the single source of truth DashboardScreen, CaseListScreen and
//DiaryScreen all read from, so the scoping rule can never drift between screens.
export function hasGlobalVisibility(role: UserRole): boolean {
  return role === 'Admin' || role === 'Director';
}

//Roles that may create/edit case , diary items and narrations (Messenger is read/attach-only)
export function canEditRecords(role: UserRole): boolean {
  return role !== 'Messenger'
}