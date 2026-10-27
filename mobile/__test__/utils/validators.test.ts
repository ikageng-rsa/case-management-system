import {
  isValidEmail,
  isValidSAPhoneNumber,
  isRequired,
  checkPasswordStrength,
  isValidFileReference,
  isValidIsoDate,
} from '@/utils/validators';

import { describe, expect, it } from '@jest/globals';
describe('isValidEmail', () => {
  it('accepts a standard email', () => {
    expect(isValidEmail('ronewa@firm.co.za')).toBe(true);
  });

  it('trims surrounding whitespace before validating', () => {
    expect(isValidEmail('  ronewa@firm.co.za  ')).toBe(true);
  });

  it('rejects a string with no @', () => {
    expect(isValidEmail('ronewa.firm.co.za')).toBe(false);
  });

  it('rejects a string with no domain', () => {
    expect(isValidEmail('ronewas@')).toBe(false);
  });

  it('rejects a string with spaces', () => {
    expect(isValidEmail('rone wa@firm.co.za')).toBe(false);
  });
});

describe('isValidSAPhoneNumber', () => {
  it('accepts a local 0-prefixed number', () => {
    expect(isValidSAPhoneNumber('0821234567')).toBe(true);
  });

  it('accepts an international +27 number', () => {
    expect(isValidSAPhoneNumber('+27821234567')).toBe(true);
  });

  it('accepts a number formatted with spaces', () => {
    expect(isValidSAPhoneNumber('082 123 4567')).toBe(true);
  });

  it('accepts a number formatted with dashes', () => {
    expect(isValidSAPhoneNumber('082-123-4567')).toBe(true);
  });

  it('rejects a number missing digits', () => {
    expect(isValidSAPhoneNumber('08212345')).toBe(false);
  });

  it('rejects a landline-style number without country/trunk prefix', () => {
    expect(isValidSAPhoneNumber('821234567')).toBe(false);
  });
});

describe('isRequired', () => {
  it('is false for undefined', () => {
    expect(isRequired(undefined)).toBe(false);
  });

  it('is false for null', () => {
    expect(isRequired(null)).toBe(false);
  });

  it('is false for an empty string', () => {
    expect(isRequired('')).toBe(false);
  });

  it('is false for a whitespace-only string', () => {
    expect(isRequired('   ')).toBe(false);
  });

  it('is true for a non-empty string', () => {
    expect(isRequired('Litigation')).toBe(true);
  });
});

describe('checkPasswordStrength', () => {
  it('scores an empty password as very weak', () => {
    expect(checkPasswordStrength('')).toEqual({ score: 0, label: 'Very weak', meetsMinimum: false });
  });

  it('scores a long lowercase-only password as weak', () => {
    const result = checkPasswordStrength('longpassword');
    expect(result.score).toBe(1);
    expect(result.meetsMinimum).toBe(false);
  });

  it('meets minimum with 8+ chars, upper, lower, and a number', () => {
    const result = checkPasswordStrength('Firm2026');
    expect(result.meetsMinimum).toBe(true);
    expect(result.score).toBeGreaterThanOrEqual(3);
  });

  it('scores highest with length, case mix, number, and symbol', () => {
    const result = checkPasswordStrength('Firm2026!Legal');
    expect(result.score).toBe(4);
    expect(result.label).toBe('Very strong');
    expect(result.meetsMinimum).toBe(true);
  });
});

describe('isValidFileReference', () => {
  it('accepts a well-formed reference', () => {
    expect(isValidFileReference('TM/LIT/014/2026')).toBe(true);
  });

  it('is case-insensitive', () => {
    expect(isValidFileReference('tm/lit/014/2026')).toBe(true);
  });

  it('rejects a reference missing a segment', () => {
    expect(isValidFileReference('TM/LIT/2026')).toBe(false);
  });

  it('rejects a reference with a non-4-digit year', () => {
    expect(isValidFileReference('TM/LIT/014/26')).toBe(false);
  });
});


describe('isValidIsoDate', () => {
  it('accepts real dates', () => {
    expect(isValidIsoDate('2026-11-05')).toBe(true);
    expect(isValidIsoDate('2028-02-29')).toBe(true);
  });
  it('rejects malformed or impossible dates', () => {
    expect(isValidIsoDate('2026-02-31')).toBe(false);
    expect(isValidIsoDate('2026-13-01')).toBe(false);
    expect(isValidIsoDate('05/11/2026')).toBe(false);
    expect(isValidIsoDate('')).toBe(false);
  });
});
