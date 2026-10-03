import {UserRole} from '@/models/index';

export const ROLES: Record<UserRole, UserRole> ={
    Admin: 'Admin',
    Director: 'Director',
    CA: 'CA',
    Secretary: 'Secretary',
    Messanger: 'Messanger'
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