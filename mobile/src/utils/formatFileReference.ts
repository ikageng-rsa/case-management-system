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
  const match = reference.match(/^([A-Z]{1,4})\/([A-Z]{2,6})\/(\d{1,4})\/(\d{4})$/i);
  if (!match) {
    return null;
  }
  const [, attorneyInitials, matterTypeCode, sequence, year] = match;
  return {
    attorneyInitials: attorneyInitials.toUpperCase(),
    matterTypeCode: matterTypeCode.toUpperCase(),
    sequence: parseInt(sequence, 10),
    year: parseInt(year, 10),
  };
}

/** Initials for InitialsAvatar — "Thandiwe Mokoena" → "TM". */
export function getInitials(fullName: string): string {
  const parts = fullName.trim().split(/\s+/).filter(Boolean);
  if (parts.length === 0) {
    return '?';
  }
  if (parts.length === 1) {
    return parts[0].slice(0, 2).toUpperCase();
  }
  return `${parts[0][0]}${parts[parts.length - 1][0]}`.toUpperCase();
}
