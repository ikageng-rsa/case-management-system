export const palette = {
    // Ink
    ink900: '#16202b',

    // Paper
    paper000: '#ffffff',
    paper050: '#fdfaf5',
    paper075: '#faf8f4',
    paper100: '#f6f4ef',

    // Gold
    gold500: '#c79a55',
    gold600: '#d4a961',
    gold800: '#8a5d28',
    gold900: '#7a5220',

    // Status
    green700: '#3d5a44',
    teal700: '#276060',
    rust700: '#a3452f',
    rust800: '#8d3a26',
    slate600: '#4a5b70',

    // Badge tints
    tintSlate: '#6b7c93',
    tintTeal: '#2f6b6b',
    tintGreen: '#4a6b52',
    tintRust: '#a3452f',
    tintGold: '#c79a55',

    white: '#ffffff',
    black: '#000000',
} as const;

/*
 * One family per weight, since Android ignores fontWeight on custom fonts.
 * These are the names the fonts are loaded under with useFonts (see guide.md).
 */
export const fontFamily = {
    sans: 'IBMPlexSans-Regular',
    sansMedium: 'IBMPlexSans-Medium',
    sansSemibold: 'IBMPlexSans-SemiBold',
    mono: 'IBMPlexMono-Regular',
    monoMedium: 'IBMPlexMono-Medium',
    monoSemibold: 'IBMPlexMono-SemiBold',
    display: 'Newsreader-Regular',
    displaySemibold: 'Newsreader-SemiBold',
} as const;

// The web type scale in px (1rem = 16px).
export const fontSize = {
    xs3: 9,
    xs2: 9.5,
    xs: 10,
    sm: 10.5,
    base: 11.5,
    md: 12.5,
    lg: 13,
    xl: 17,
    displaySm: 22,
    displayMd: 27,
    displayLg: 30,
    displayXl: 32,
} as const;

export const tracking = {
    label: 0.95,
    labelWide: 1.23,
} as const;

export const radius = {
    xs: 3,
    sm: 4,
    base: 6,
    md: 7,
    lg: 10,
} as const;

export const space = {
    2: 2,
    4: 4,
    6: 6,
    8: 8,
    10: 10,
    12: 12,
    14: 14,
    16: 16,
    18: 18,
    20: 20,
    24: 24,
    26: 26,
    32: 32,
} as const;

export const layout = {
    sidebarWidth: 240,
    topbarHeight: 60,
} as const;
