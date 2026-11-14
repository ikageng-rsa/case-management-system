import { fontFamily as kitFonts } from '@/ui/theme/tokens';

/**
 * Newsreader (serif) for headings, IBM Plex Sans for body/UI, IBM Plex Mono for
 * labels and references. The fonts are loaded in app/_layout.tsx under the
 * names in `fontFamily`.
 *
 * Android ignores `fontWeight` on custom fonts, so weight is a separate family
 * rather than a style. Use `fontFamily.sansSemibold` (etc.) instead of adding
 * `fontWeight` to a text style.
 */
export const fontFamily = {
  serif: kitFonts.display,
  serifSemibold: kitFonts.displaySemibold,
  sans: kitFonts.sans,
  sansMedium: kitFonts.sansMedium,
  sansSemibold: kitFonts.sansSemibold,
  mono: kitFonts.mono,
  monoMedium: kitFonts.monoMedium,
  monoSemibold: kitFonts.monoSemibold,
};

export const typography = {
  // Large serif display — splash/empty states, dashboard hero number labels
  display: { fontFamily: fontFamily.serif, fontSize: 32, lineHeight: 38 },
  // Screen titles
  h1: { fontFamily: fontFamily.serif, fontSize: 26, lineHeight: 32 },
  h2: { fontFamily: fontFamily.serif, fontSize: 20, lineHeight: 26 },
  h3: { fontFamily: fontFamily.serifSemibold, fontSize: 17, lineHeight: 23 },
  // Body copy stays sans for on-screen legibility
  body: { fontFamily: fontFamily.sans, fontSize: 14, lineHeight: 20 },
  bodyBold: { fontFamily: fontFamily.sansSemibold, fontSize: 14, lineHeight: 20 },
  caption: { fontFamily: fontFamily.sans, fontSize: 12, lineHeight: 17 },
  captionBold: { fontFamily: fontFamily.sansSemibold, fontSize: 12, lineHeight: 17 },
  // Small tracked-out mono label — "CASE FILE", stamp text, section eyebrows
  overline: {
    fontFamily: fontFamily.monoSemibold,
    fontSize: 10.5,
    lineHeight: 14,
    letterSpacing: 1.2,
    textTransform: 'uppercase' as const,
  },
};

export type AppTypography = typeof typography;
