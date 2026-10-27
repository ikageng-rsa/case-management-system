import { buildFileReference, parseFileReference } from '@/utils/formatFileReference';
import { describe, expect, it } from '@jest/globals';

describe('buildFileReference', () => {
  it('builds a reference with zero-padded sequence', () => {
    expect(buildFileReference('TM', 'LIT', 14, 2026)).toBe('TM/LIT/014/2026');
  });

  it('uppercases initials and matter type code', () => {
    expect(buildFileReference('tm', 'lit', 5, 2026)).toBe('TM/LIT/005/2026');
  });

  it('defaults year to the current year when omitted', () => {
    const ref = buildFileReference('RD', 'FAM', 1);
    expect(ref).toMatch(/^RD\/FAM\/001\/\d{4}$/);
  });

  it('handles a sequence beyond 3 digits without truncating', () => {
    expect(buildFileReference('DM', 'COM', 1234, 2026)).toBe('DM/COM/1234/2026');
  });
});

describe('parseFileReference', () => {
  it('parses a well-formed reference', () => {
    expect(parseFileReference('TM/LIT/014/2026')).toEqual({
      attorneyInitials: 'TM',
      matterTypeCode: 'LIT',
      sequence: 14,
      year: 2026,
    });
  });

  it('is case-insensitive and normalizes to uppercase', () => {
    expect(parseFileReference('tm/lit/014/2026')).toEqual({
      attorneyInitials: 'TM',
      matterTypeCode: 'LIT',
      sequence: 14,
      year: 2026,
    });
  });

  it('returns null for a malformed reference', () => {
    expect(parseFileReference('not-a-reference')).toBeNull();
  });

  it('returns null for a reference missing the year', () => {
    expect(parseFileReference('TM/LIT/014')).toBeNull();
  });

  it('round-trips with buildFileReference', () => {
    const built = buildFileReference('AB', 'CRIM', 42, 2027);
    expect(parseFileReference(built)).toEqual({
      attorneyInitials: 'AB',
      matterTypeCode: 'CRIM',
      sequence: 42,
      year: 2027,
    });
  });
});
