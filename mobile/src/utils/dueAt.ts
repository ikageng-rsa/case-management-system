/**
 * Diary due moments. The API takes an ISO moment with its offset ("2026-11-14T09:00:00+02:00") and answers in UTC,
 * while people think in a calendar day and a clock time on the phone. These helpers convert between the two by
 * hand, so the result does not depend on the device's ICU/locale data.
 */
const pad = (n: number) => String(n).padStart(2, '0');
const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

export const DEFAULT_DUE_TIME = '09:00';

export function isValidTime(value: string): boolean {
    return /^([01]\d|2[0-3]):[0-5]\d$/.test(value);
}

/** "+02:00" style offset of a moment on the phone's own clock. */
function offsetOf(date: Date): string {
    const minutes = -date.getTimezoneOffset();
    const sign = minutes >= 0 ? '+' : '-';
    const abs = Math.abs(minutes);
    return `${sign}${pad(Math.floor(abs / 60))}:${pad(abs % 60)}`;
}

export function toOffsetIso(date: Date): string {
    return (
        `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}` +
        `T${pad(date.getHours())}:${pad(date.getMinutes())}:00${offsetOf(date)}`
    );
}

/** A calendar day ("2026-11-14") and clock time ("09:00") on the phone's clock → ISO with offset, or null if invalid. */
export function buildDueAt(day: string, time: string): string | null {
    const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(day);
    if (!m || !isValidTime(time)) {
        return null;
    }
    const [y, mo, d] = [Number(m[1]), Number(m[2]), Number(m[3])];
    const date = new Date(y, mo - 1, d, Number(time.slice(0, 2)), Number(time.slice(3)), 0, 0);
    // Rejects 2026-02-31, which Date would roll over to March.
    if (date.getFullYear() !== y || date.getMonth() !== mo - 1 || date.getDate() !== d) {
        return null;
    }
    return toOffsetIso(date);
}

/** Splits a moment into the phone-local day and time the edit form shows. */
export function splitDueAt(iso: string): { day: string; time: string } {
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) {
        return { day: iso.slice(0, 10), time: DEFAULT_DUE_TIME };
    }
    return { day: `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`, time: `${pad(d.getHours())}:${pad(d.getMinutes())}` };
}

/** "14 Nov 2026, 09:00" on the phone's clock. */
export function formatDue(iso?: string): string {
    if (!iso) {
        return '';
    }
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) {
        return iso;
    }
    return `${pad(d.getDate())} ${MONTHS[d.getMonth()]} ${d.getFullYear()}, ${pad(d.getHours())}:${pad(d.getMinutes())}`;
}
