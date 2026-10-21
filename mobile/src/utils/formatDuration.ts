/** 95 -> "1h 35m", 60 -> "1h", 25 -> "25m", 0 -> "0m". */
export function formatDuration(totalMinutes: number): string {
    const safe = Math.max(0, Math.round(totalMinutes || 0));
    const h = Math.floor(safe / 60);
    const m = safe % 60;
    if (h && m) return `${h}h ${m}m`;
    if (h) return `${h}h`;
    return `${m}m`;
}
