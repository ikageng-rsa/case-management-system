import { formatDateShort, formatDateLong, formatRelativeDueDate, isOverdue } from '@/utils/formatDate';
import { beforeEach,afterEach ,describe, expect, it } from '@jest/globals';
import { jest } from '@jest/globals';                            

describe('formatDateShort', () => {
  it('formats an ISO date as dd MMM yyyy', () => {
    expect(formatDateShort('2026-09-07')).toBe('07 Sep 2026');
  });

  it('returns the original string for an invalid date', () => {
    expect(formatDateShort('not-a-date')).toBe('not-a-date');
  });
});

describe('formatDateLong', () => {
  it('formats an ISO date with weekday and full month', () => {
    // 2026-09-07 is a Monday
    expect(formatDateLong('2026-09-07')).toBe('Monday, 07 September 2026');
  });
});

describe('formatRelativeDueDate', () => {
  beforeEach(() => {
    // jest.useFakeTimers + setSystemTime patches the engine's actual clock
    // (unlike reassigning Date.now, which `new Date()` doesn't consistently
    // read from across Node/Hermes versions — confirmed by this exact test
    // silently going stale as real time moved past the old fixed date).
    jest.useFakeTimers();
    jest.setSystemTime(new Date('2026-09-01T09:00:00Z'));
  });

  afterEach(() => {
    jest.useRealTimers();
  });

  it('says "Due today" for today', () => {
    expect(formatRelativeDueDate('2026-09-01')).toBe('Due today');
  });

  it('says "Due tomorrow" for tomorrow', () => {
    expect(formatRelativeDueDate('2026-09-02')).toBe('Due tomorrow');
  });

  it('says "Due in N days" for a future date beyond tomorrow', () => {
    expect(formatRelativeDueDate('2026-09-05')).toBe('Due in 4 days');
  });

  it('says "1 day overdue" for yesterday', () => {
    expect(formatRelativeDueDate('2026-08-31')).toBe('1 day overdue');
  });

  it('says "N days overdue" for further in the past', () => {
    expect(formatRelativeDueDate('2026-08-25')).toBe('7 days overdue');
  });

  it('falls back to the raw string for an invalid date', () => {
    expect(formatRelativeDueDate('garbage')).toBe('garbage');
  });
});

describe('isOverdue', () => {
  beforeEach(() => {
    jest.useFakeTimers();
    jest.setSystemTime(new Date('2026-09-01T09:00:00Z'));
  });

  afterEach(() => {
    jest.useRealTimers();
  });

  it('is false for today', () => {
    expect(isOverdue('2026-09-01')).toBe(false);
  });

  it('is false for a future date', () => {
    expect(isOverdue('2026-09-02')).toBe(false);
  });

  it('is true for a past date', () => {
    expect(isOverdue('2026-08-31')).toBe(true);
  });

  it('is false for an invalid date rather than throwing', () => {
    expect(isOverdue('garbage')).toBe(false);
  });
});
