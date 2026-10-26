import { shouldLock } from '../../src/features/security/lockPolicy';
import { routeForNotification, safeId } from '@/utils/deepLinks';
import { planReminders } from '@/features/darzation/services/reminderPlanner';
import { Darzation } from '@/models/index';
import { beforeEach, describe, expect, it } from '@jest/globals';
import { jest } from '@jest/globals';
describe('app lock policy', () => {
  it('locks only after the app was away long enough', () => {
    expect(shouldLock(null, 1000)).toBe(false);
    expect(shouldLock(0, 30_000, 60_000)).toBe(false);
    expect(shouldLock(0, 60_000, 60_000)).toBe(true);
  });
});

describe('notification deep links', () => {
  it('accepts plain ids only', () => {
    expect(safeId('4987e0f4-dcac-4673-89a6-21930cbddb35')).not.toBeNull();
    expect(safeId('../../admin')).toBeNull();
    expect(safeId('a/b')).toBeNull();
    expect(safeId({})).toBeNull();
  });

  it('routes to the diary item, then the case, else the inbox', () => {
    expect(routeForNotification({ darzationId: 'd1', caseId: 'c1' })).toEqual({ pathname: '/diary/[darzationId]', params: { darzationId: 'd1' } });
    expect(routeForNotification({ caseId: 'c1' })).toEqual({ pathname: '/cases/[caseId]', params: { caseId: 'c1' } });
    expect(routeForNotification({ darzationId: '../x' })).toBe('/more/notifications');
    expect(routeForNotification(null)).toBe('/more/notifications');
  });
});

const item = (id: string, dueDate: string, status: Darzation['status'] = 'Pending'): Darzation =>
  ({ id, caseId: 'c1', ownerId: 'u', title: 'Client X summons', nextAction: 'x', nextActionDate: dueDate, dueDate, priority: 'High', status, steps: [], assignedUserIds: [], overdue: false, visibleToRoles: [] }) as Darzation;

describe('local diary reminders (offline fallback)', () => {
  const now = new Date(2026, 9, 23, 10, 0); // 23 Oct 2026 10:00 local

  it('plans a due-day and an overdue reminder at 08:00 and skips what has passed', () => {
    const plans = planReminders([item('a', '2026-10-25'), item('b', '2026-10-23'), item('old', '2026-10-20')], now);
    const ids = plans.map(p => p.identifier).sort();
    // b: due-day 08:00 already passed today, overdue (24 Oct) still ahead. old: everything passed.
    expect(ids).toEqual(['diary:a:due', 'diary:a:overdue', 'diary:b:overdue']);
    const due = plans.find(p => p.identifier === 'diary:a:due')!;
    expect(due.date.getHours()).toBe(8);
    expect(due.date.getDate()).toBe(25);
  });

  it('skips finished items and unsynced (temporary) ones', () => {
    expect(planReminders([item('d', '2026-10-30', 'Done'), item('tmp_0123456789abcdef', '2026-10-30')], now)).toEqual([]);
  });

  it('never puts the client or matter on the lock screen', () => {
    const [plan] = planReminders([item('a', '2026-10-25')], now);
    expect(`${plan.title} ${plan.body}`).not.toMatch(/Client X|summons/);
  });

  it('keeps within the 64-notification OS limit', () => {
    const many = Array.from({ length: 80 }, (_, i) => item(`i${i}`, '2026-12-01'));
    expect(planReminders(many, now).length).toBeLessThanOrEqual(60);
  });
});
