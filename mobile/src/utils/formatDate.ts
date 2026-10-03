const MS_PER_DAY = 1000 * 60 * 60 * 24;

const SHORT_MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
const LONG_MONTHS = [
  'January',
  'February',
  'March',
  'April',
  'May',
  'June',
  'July',
  'August',
  'September',
  'October',
  'November',
  'December',
];
const WEEKDAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

function parseISODate(iso: string): Date | null {
  const d = new Date(iso);
  return Number.isNaN(d.getTime()) ? null : d;
}

/**
 * Hand-rolled rather than toLocaleDateString for the same reason as
 * formatCurrency: locale/ICU output (e.g. "Sept" vs "Sep") varies by JS
 * engine and ICU data build, which is exactly the kind of inconsistency
 * that should be deterministic across Android devices. Dates are read as
 * UTC-based calendar dates (no time-of-day) to avoid timezone drift on
 * date-only ISO strings like "2026-09-07".
 */
export function formatDateShort(iso: string): string {
  const d = parseISODate(iso);
  if (!d) {
    return iso;
  }
  const day = String(d.getUTCDate()).padStart(2, '0');
  return `${day} ${SHORT_MONTHS[d.getUTCMonth()]} ${d.getUTCFullYear()}`;
}

export function formatDateLong(iso: string): string {
  const d = parseISODate(iso);
  if (!d) {
    return iso;
  }
  const day = String(d.getUTCDate()).padStart(2, '0');
  return `${WEEKDAYS[d.getUTCDay()]}, ${day} ${LONG_MONTHS[d.getUTCMonth()]} ${d.getUTCFullYear()}`;
}

/**
 * "3 days overdue" / "Due today" / "Due in 4 days" — used on Diary cards so
 * an attorney scanning the list doesn't have to mentally subtract dates.
 */
export function formatRelativeDueDate(iso: string): string {
  const due = parseISODate(iso);
  if (!due) {
    return iso;
  }

  const today = new Date();
  const todayUTC = Date.UTC(today.getFullYear(), today.getMonth(), today.getDate());
  const dueUTC = Date.UTC(due.getUTCFullYear(), due.getUTCMonth(), due.getUTCDate());

  const diffDays = Math.round((dueUTC - todayUTC) / MS_PER_DAY);

  if (diffDays === 0) {
    return 'Due today';
  }
  if (diffDays === 1) {
    return 'Due tomorrow';
  }
  if (diffDays > 1) {
    return `Due in ${diffDays} days`;
  }
  if (diffDays === -1) {
    return '1 day overdue';
  }
  return `${Math.abs(diffDays)} days overdue`;
}

export function isOverdue(iso: string): boolean {
  const due = parseISODate(iso);
  if (!due) {
    return false;
  }

  const today = new Date();
  const todayUTC = Date.UTC(today.getFullYear(), today.getMonth(), today.getDate());
  const dueUTC = Date.UTC(due.getUTCFullYear(), due.getUTCMonth(), due.getUTCDate());

  return dueUTC < todayUTC;
}
