import { Platform } from 'react-native';

/**
 * Serif for headings, sans for body/UI chrome — the classic letterhead
 * pairing. Both fall back to platform system fonts, so this looks correct
 * with zero custom font files bundled; drop real weights (e.g. Playfair
 * Display / Source Serif) into android/app/src/main/assets/fonts later for
 * a closer match without touching any screen.
 */
export const fontFamily = {
  serif: Platform.select({ android: 'serif', ios: 'Georgia', default: 'serif' }),
  serifMedium: Platform.select({ android: 'serif', ios: 'Georgia-Bold', default: 'serif' }),
  sans: Platform.select({ android: 'sans-serif', ios: 'System', default: 'System' }),
  sansMedium: Platform.select({ android: 'sans-serif-medium', ios: 'System', default: 'System' }),
};

export const typography = {
  // Large serif display — splash/empty states, dashboard hero number labels
  display: { fontFamily: fontFamily.serif, fontSize: 30, fontWeight: '700' as const },
  // Screen titles — serif, matches a document heading
  h1: { fontFamily: fontFamily.serif, fontSize: 24, fontWeight: '700' as const },
  h2: { fontFamily: fontFamily.serif, fontSize: 18, fontWeight: '700' as const },
  h3: { fontFamily: fontFamily.serifMedium, fontSize: 16, fontWeight: '600' as const },
  // Body copy stays sans for on-screen legibility
  body: { fontFamily: fontFamily.sans, fontSize: 14, fontWeight: '400' as const },
  bodyBold: { fontFamily: fontFamily.sansMedium, fontSize: 14, fontWeight: '600' as const },
  caption: { fontFamily: fontFamily.sans, fontSize: 12, fontWeight: '400' as const },
  captionBold: { fontFamily: fontFamily.sansMedium, fontSize: 12, fontWeight: '600' as const },
  // Small tracked-out label — "CASE FILE", "PRIVILEGED" stamp text, section eyebrows
  overline: {
    fontFamily: fontFamily.sansMedium,
    fontSize: 11,
    fontWeight: '700' as const,
    letterSpacing: 1.2,
    textTransform: 'uppercase' as const,
  },
};

export type AppTypography = typeof typography;
