//Locked again after the app has been out of sight  this long
export const LOCK_AFTER_MS = Number(process.env.EXPO_PUBLIC_APP_LOCK_AFTER_SECORDS ?? 60) * 1000;

//Pure decision, so it can be tested: should coming back to the app require unlocking?
export function shouldLock(backgroundedAt: number | null, now: number, lockAfterMs = LOCK_AFTER_MS): boolean{
    return backgroundedAt !== null && now - backgroundedAt >= lockAfterMs;
}