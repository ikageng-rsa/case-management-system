/**
 * Palette intent: navy + gold reads as "chambers/letterhead" rather than
 * generic corporate blue; parchment background and hairline borders give
 * screens a paper-adjacent warmth instead of flat app-grey. Status colors
 * (green/gold/burgundy/red) double as the confidentiality and case-status
 * vocabulary used across Cases, Documents, and the Gantt-style reporting.
 */
export const colors = {
  // Brand
  navy: '#1F3B57',
  navyDark: '#152A3F',
  navyTint: '#E8EDF2', // navy at ~10% for selected/active backgrounds
  gold: '#B8860B',
  goldTint: '#F6EFDD',
  burgundy: '#6E2233', // "privileged" / seal / emphasis accent
  burgundyTint: '#F3E4E7',

  // Status (reused by CaseStatusBadge, confidentiality tags, diary alerts)
  green: '#2E7D32',
  greenTint: '#E7F2E8',
  red: '#8B2E2E',
  redTint: '#F5E6E6',
  purple: '#5E35B1',
  purpleTint: '#EDE7F8',
  danger: '#B3261E',

  // Surfaces
  background: '#F7F5F0', // warm parchment, not flat grey
  surface: '#FFFFFF',
  surfaceRaised: '#FFFFFF',
  border: '#E4DED2', // warm hairline
  divider: '#D9D2C4',

  // Text
  textPrimary: '#1A1F26',
  textSecondary: '#5A6472',
  textOnDark: '#FFFFFF',
  textMuted: '#8A8F98',

  white: '#FFFFFF',
  black: '#000000',
  transparent: 'transparent',

  primary: '#1F3B57', // alias for navy
};

export type AppColors = typeof colors;
