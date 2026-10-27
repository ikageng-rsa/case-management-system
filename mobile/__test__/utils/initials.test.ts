import { getInitials } from '@/utils/initials';
import { getInitials as getInitialsLegacyPath } from '@/utils/formatFileReference';
import { describe, expect, it } from '@jest/globals';

describe('getInitials', () => {
  it('takes first and last name initials for a full name', () => {
    expect(getInitials('Thandiwe Mokoena')).toBe('TM');
  });

  it('uses first two letters for a single-word name', () => {
    expect(getInitials('Beyoncé')).toBe('BE');
  });

  it('ignores extra whitespace between names', () => {
    expect(getInitials('  Ronewa   Diana  ')).toBe('RD');
  });

  it('uses first and last initials for a name with a middle name', () => {
    expect(getInitials('John Michael Smith')).toBe('JS');
  });

  it('returns a placeholder for an empty string', () => {
    expect(getInitials('')).toBe('?');
  });
});

describe('getInitials re-exported from formatFileReference (back-compat)', () => {
  it('still works via the old import path', () => {
    expect(getInitialsLegacyPath('Thandiwe Mokoena')).toBe('TM');
  });
});
