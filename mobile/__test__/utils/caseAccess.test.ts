import { canSeeCase, visibleCasesFor, visibleDarzationsFor, visibleNarrationsFor } from '@/utils/caseAccess';
import { Case, Darzation, Narration } from '@/models/index';
import { beforeEach, describe, expect, it } from '@jest/globals';
import { jest } from '@jest/globals';                            

const mk = (id: string, assigned: string[]): Case => ({
  id,
  fileReference: id,
  clientId: 'c',
  clientName: 'Client',
  matterType: 'Lit',
  status: 'Open',
  priority: 'Medium',
  leadAttorneyId: 'x',
  assignedUserIds: assigned,
  openedDate: '2026-01-01',
});
const cases = [mk('A', ['u1']), mk('B', ['u2']), mk('C', ['u1', 'u2'])];
const diary = (id: string, caseId: string, extra: Partial<Darzation> = {}): Darzation => ({
  id,
  caseId,
  ownerId: 'nobody',
  title: id,
  nextAction: id,
  nextActionDate: '2026-01-01',
  dueDate: '2026-01-01',
  priority: 'Low',
  status: 'Pending',
  steps: [],
  assignedUserIds: [],
  overdue: false,
  visibleToRoles: [],
  ...extra,
});
const narration = (id: string, caseId: string, authorId = 'nobody'): Narration => ({
  id,
  caseId,
  authorId,
  activityType: 'Call',
  description: id,
  timeSpentMinutes: 5,
  billable: true,
  documentIds: [],
  createdAt: '2026-01-01',
});

describe('caseAccess', () => {
  const ca = { id: 'u1', role: 'CA' as const };
  const director = { id: 'd', role: 'Director' as const };

  it('director and admin see everything', () => {
    expect(visibleCasesFor(cases, director)).toHaveLength(3);
    expect(visibleCasesFor(cases, { id: 'a', role: 'Admin' })).toHaveLength(3);
  });

  it('other roles only see cases they are assigned to', () => {
    expect(visibleCasesFor(cases, ca).map(c => c.id)).toEqual(['A', 'C']);
  });

  it('no user means no access', () => {
    expect(visibleCasesFor(cases, null)).toEqual([]);
    expect(canSeeCase(cases[0], null)).toBe(false);
  });

  it('canSeeCase mirrors the scoping rule', () => {
    expect(canSeeCase(cases[1], ca)).toBe(false);
    expect(canSeeCase(cases[1], director)).toBe(true);
  });

  it('diary is scoped to visible cases or direct assignment', () => {
    const items = [diary('1', 'A'), diary('2', 'B'), diary('3', 'B', { assignedUserIds: ['u1'] }), diary('4', 'B', { ownerId: 'u1' })];
    expect(visibleDarzationsFor(items, cases, ca).map(d => d.id)).toEqual(['1', '3', '4']);
    expect(visibleDarzationsFor(items, cases, director)).toHaveLength(4);
  });

  it('narrations are scoped to visible cases or own authorship', () => {
    const items = [narration('1', 'A'), narration('2', 'B'), narration('3', 'B', 'u1')];
    expect(visibleNarrationsFor(items, cases, ca).map(n => n.id)).toEqual(['1', '3']);
  });
});
