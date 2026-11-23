import type { TextStyle } from 'react-native';
import { fontFamily, fontSize, tracking } from './tokens';

export const typography = {
    h1: { fontFamily: fontFamily.display, fontSize: fontSize.displayLg, lineHeight: 36 },
    h2: { fontFamily: fontFamily.display, fontSize: fontSize.displayMd, lineHeight: 33 },
    h3: { fontFamily: fontFamily.display, fontSize: fontSize.displaySm, lineHeight: 28 },
    h4: { fontFamily: fontFamily.display, fontSize: fontSize.xl, lineHeight: 23 },
    h5: { fontFamily: fontFamily.sansMedium, fontSize: fontSize.lg, lineHeight: 19 },
    h6: { fontFamily: fontFamily.sansMedium, fontSize: fontSize.md, lineHeight: 18 },

    body: { fontFamily: fontFamily.sans, fontSize: fontSize.md, lineHeight: 18 },
    small: { fontFamily: fontFamily.sans, fontSize: fontSize.base, lineHeight: 16 },

    // Stat card labels, table headers and sidebar section titles
    label: {
        fontFamily: fontFamily.monoMedium,
        fontSize: fontSize.xs2,
        letterSpacing: tracking.labelWide,
        textTransform: 'uppercase',
    },
    // Form field labels
    fieldLabel: {
        fontFamily: fontFamily.monoSemibold,
        fontSize: fontSize.xs,
        letterSpacing: tracking.labelWide,
        textTransform: 'uppercase',
    },
    // References, dates and other mono metadata
    meta: { fontFamily: fontFamily.mono, fontSize: fontSize.xs2, letterSpacing: tracking.label },

    statValue: { fontFamily: fontFamily.display, fontSize: fontSize.displayXl, lineHeight: 38 },
} satisfies Record<string, TextStyle>;
