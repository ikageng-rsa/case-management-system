import { canAccessTab, canAdministerUsers, canEditRecords, hasGlobalVisibility, TAB_ACCESS } from '@/constants/roles';
import { describe, expect, it } from '@jest/globals';

describe('roles', () => {
  it('has no Billing tab any more', () => {
    expect(Object.keys(TAB_ACCESS)).not.toContain('Billing');
    expect(canAccessTab('Billing', 'Admin')).toBe(false);
  });
  it('messenger cannot open cases or diary but can open More', () => {
    expect(canAccessTab('Cases', 'Messenger')).toBe(false);
    expect(canAccessTab('Diary', 'Messenger')).toBe(false);
    expect(canAccessTab('More', 'Messenger')).toBe(true);
  });
  it('only Admin and Director have global visibility and user admin', () => {
    expect(hasGlobalVisibility('Director')).toBe(true);
    expect(hasGlobalVisibility('Admin')).toBe(true);
    expect(hasGlobalVisibility('CA')).toBe(false);
    expect(canAdministerUsers('Secretary')).toBe(false);
  });
  it('messenger cannot edit records', () => {
    expect(canEditRecords('Messenger')).toBe(false);
    expect(canEditRecords('CA')).toBe(true);
  });
});
