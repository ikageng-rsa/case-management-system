/**
 * A thin wrapper around console.* that redacts keys commonly holding client
 * PII (names, contact details, ID/FICA numbers) before anything reaches
 * device logs or a crash reporter. Use this instead of console.log anywhere
 * a payload might contain a ClientProfile, Case, or Narration object —
 * device logs are exactly the kind of place POPIA compliance reviews check.
 */
const SENSITIVE_KEYS = [
  'fullName',
  'clientName',
  'email',
  'contactNumber',
  'phone',
  'idNumber',
  'passportNumber',
  'address',
  'ficaVerified',
  'password',
  'token',
];

function redact(value: unknown, seen = new WeakSet<object>()): unknown {
  if (value === null || typeof value !== 'object') {
    return value;
  }
  if (seen.has(value as object)) {
    return '[circular]';
  }
  seen.add(value as object);

  if (Array.isArray(value)) {
    return value.map(v => redact(v, seen));
  }

  const out: Record<string, unknown> = {};
  for (const [key, val] of Object.entries(value as Record<string, unknown>)) {
    out[key] = SENSITIVE_KEYS.includes(key) ? '[redacted]' : redact(val, seen);
  }
  return out;
}

export const popiaLogger = {
  log(message: string, payload?: unknown) {
    if (__DEV__) {
      console.log(message, payload !== undefined ? redact(payload) : '');
    }
  },
  warn(message: string, payload?: unknown) {
    console.warn(message, payload !== undefined ? redact(payload) : '');
  },
  error(message: string, payload?: unknown) {
    console.error(message, payload !== undefined ? redact(payload) : '');
  },
};
