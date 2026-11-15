import { alpha, mix } from './color';
import { palette } from './tokens';

export type ThemeMode = 'light' | 'dark';

export type Tone = 'primary' | 'secondary' | 'success' | 'info' | 'warning' | 'danger' | 'dark' | 'light';

export interface ToneColors {
    bg: string;
    fg: string;
    pressed: string;
    tint: string;
}

const toneBase: Record<Tone, { bg: string; fg: string }> = {
    primary: { bg: palette.gold500, fg: palette.ink900 },
    secondary: { bg: palette.slate600, fg: palette.paper000 },
    success: { bg: palette.green700, fg: palette.paper000 },
    info: { bg: palette.teal700, fg: palette.paper000 },
    warning: { bg: palette.gold800, fg: palette.paper000 },
    danger: { bg: palette.rust700, fg: palette.paper000 },
    dark: { bg: palette.ink900, fg: palette.paper000 },
    light: { bg: palette.paper100, fg: palette.ink900 },
};

// Text and borders on an ink ground, the same in either mode.
export const inkColors = {
    onInk: palette.white,
    onInkMuted: alpha(palette.white, 0.7),
    onInkCaption: alpha(palette.white, 0.42),
    onInkCount: alpha(palette.white, 0.38),
    onInkLabel: alpha(palette.white, 0.34),
    borderOnInk: alpha(palette.white, 0.09),
    borderOnInkStrong: alpha(palette.white, 0.18),
} as const;

function buildTheme(mode: ThemeMode) {
    const dark = mode === 'dark';
    const stateMix = dark ? palette.white : palette.black;

    const tones = Object.fromEntries(
        (Object.entries(toneBase) as [Tone, { bg: string; fg: string }][]).map(([name, { bg, fg }]) => [
            name,
            { bg, fg, pressed: mix(bg, stateMix, 0.12), tint: alpha(bg, dark ? 0.2 : 0.1) },
        ]),
    ) as Record<Tone, ToneColors>;

    const surfaces = dark
        ? {
            bg: palette.ink900,
            surface: mix(palette.ink900, palette.white, 0.05),
            surfaceRaised: mix(palette.ink900, palette.white, 0.08),
            surfaceSunken: mix(palette.ink900, palette.white, 0.02),
            line: inkColors.borderOnInk,
            lineStrong: inkColors.borderOnInkStrong,
            fg: inkColors.onInk,
            fgSecondary: inkColors.onInkMuted,
            fgMuted: inkColors.onInkCaption,
            fgSubtle: alpha(palette.white, 0.32),
            link: palette.gold600,
            linkHover: palette.gold500,
            backdrop: alpha(palette.black, 0.5),
        }
        : {
            bg: palette.paper100,
            surface: palette.paper000,
            surfaceRaised: palette.paper050,
            surfaceSunken: palette.paper075,
            line: alpha(palette.ink900, 0.05),
            lineStrong: alpha(palette.ink900, 0.1),
            fg: palette.ink900,
            fgSecondary: alpha(palette.ink900, 0.62),
            fgMuted: alpha(palette.ink900, 0.45),
            fgSubtle: alpha(palette.ink900, 0.32),
            link: palette.gold800,
            linkHover: palette.gold900,
            backdrop: alpha(palette.ink900, 0.32),
        };

    return {
        mode,
        colors: {
            ...surfaces,
            ...inkColors,
            accent: palette.gold500,
            focusRing: alpha(palette.gold500, 0.3),
            selection: alpha(palette.gold500, 0.28),
        },
        tones,
    };
}

export type Theme = ReturnType<typeof buildTheme>;

export const lightTheme: Theme = buildTheme('light');
export const darkTheme: Theme = buildTheme('dark');
