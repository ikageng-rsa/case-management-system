/*
 * The web theme leans on color-mix(); React Native has no equivalent, so the
 * same mixes are worked out here from the hex tokens.
 */
type Channels = [number, number, number];

function channels(hex: string): Channels {
    const value = hex.replace('#', '');
    const full = value.length === 3 ? value.split('').map((c) => c + c).join('') : value;

    return [0, 2, 4].map((i) => parseInt(full.slice(i, i + 2), 16)) as Channels;
}

/** color-mix(in srgb, hex amount, transparent) */
export function alpha(hex: string, amount: number): string {
    const [r, g, b] = channels(hex);

    return `rgba(${r}, ${g}, ${b}, ${amount})`;
}

/** color-mix(in srgb, other amount, base) */
export function mix(base: string, other: string, amount: number): string {
    const from = channels(base);
    const to = channels(other);

    return `#${from
        .map((c, i) => Math.round(c + (to[i] - c) * amount).toString(16).padStart(2, '0'))
        .join('')}`;
}
