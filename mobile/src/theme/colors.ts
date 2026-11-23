import { mix } from '@/ui/theme/color';
import { palette } from '@/ui/theme/tokens';

/**
 * Palette intent: ink + gold on warm paper reads as "chambers/letterhead".
 * The values come from the shared UI kit (src/ui) so the app and the web
 * product stay on one palette. The key names are the ones screens already use
 * (navy, gold, burgundy, ...), so re-skinning does not touch every screen.
 *
 * Gold is a fill/border colour only. It is too light for text on paper, so
 * text that used to be gold uses `goldText`.
 */
const onPaper = (amount: number) => mix(palette.paper000, palette.ink900, amount);

export const colors = {
  // Brand
  navy: palette.ink900,
  navyDark: mix(palette.ink900, palette.black, 0.35),
  navyTint: onPaper(0.07), // ink at ~7% for selected/active backgrounds
  gold: palette.gold500,
  goldText: palette.gold800,
  goldTint: mix(palette.paper000, palette.gold500, 0.2),
  burgundy: palette.rust800, // "privileged" / seal / emphasis accent
  burgundyTint: mix(palette.paper000, palette.rust800, 0.12),

  // Status (reused by CaseStatusBadge, confidentiality tags, diary alerts)
  green: palette.green700,
  greenTint: mix(palette.paper000, palette.green700, 0.12),
  red: palette.rust700,
  redTint: mix(palette.paper000, palette.rust700, 0.12),
  purple: palette.teal700, // "archived" / time totals; the key predates the kit palette
  purpleTint: mix(palette.paper000, palette.teal700, 0.12),
  danger: palette.rust700,

  // Surfaces
  background: palette.paper100,
  surface: palette.paper000,
  surfaceRaised: palette.paper000,
  border: onPaper(0.1), // hairline
  divider: onPaper(0.14),

  // Text
  textPrimary: palette.ink900,
  textSecondary: onPaper(0.68),
  textOnDark: palette.white,
  textMuted: onPaper(0.55),

  white: palette.white,
  black: palette.black,
  transparent: 'transparent',
};

export type AppColors = typeof colors;
