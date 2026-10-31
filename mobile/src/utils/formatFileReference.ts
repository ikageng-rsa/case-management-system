/**
 * Matches the backlog's user story: "auto-generated file reference
 * (initials+mattertype+seq+year)". e.g. buildFileReference('TM', 'LIT', 14, 2026)
 * → "TM/LIT/014/2026".
 */
export function buildFileReference(
  attorneyInitials: string,
  matterTypeCode: string,
  sequence: number,
  year: number = new Date().getFullYear(),
): string {
  const seq = String(sequence).padStart(3, '0');
  return `${attorneyInitials.toUpperCase()}/${matterTypeCode.toUpperCase()}/${seq}/${year}`;
}

export interface ParsedFileReference {
  attorneyInitials: string;
  matterTypeCode: string;
  sequence: number;
  year: number;
}

export function parseFileReference(reference: string): ParsedFileReference | null {
  const pattern =
    /^([A-Z]{1,4})\/([A-Z]{2,6})\/(\d{1,4})\/(\d{4})$/i;

  const match = pattern.exec(reference);
  if (!match) {
    return null;
  }
  const [, attorneyInitials, matterTypeCode, sequence, year] = match;
  return {
    attorneyInitials: attorneyInitials.toUpperCase(),
    matterTypeCode: matterTypeCode.toUpperCase(),
    sequence: Number.parseInt(sequence, 10),
    year: Number.parseInt(year, 10),
  };
}

// getInitials now lives in its own module (@utils/initials / @/utils/initials)   
// — re-exported here so anything still importing it from this file keeps working.
export { getInitials } from './initials';                                         