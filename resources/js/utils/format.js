// Presentation helpers for the values the API hands back as raw ISO strings
// and decimal-strings. Plain functions rather than a composable - there's no
// reactive state or lifecycle here, so a composable would only add indirection.

/**
 * Parses a date-only 'YYYY-MM-DD' as a LOCAL date.
 *
 * `new Date('2026-08-02')` is parsed as UTC midnight, which renders as Aug 1
 * anywhere west of Greenwich and is a day out for a resort in UTC+5. Splitting
 * the parts and using the numeric constructor keeps it local. Full datetime
 * strings still go through the normal parser.
 */
const toLocalDate = (value) => {
    if (value instanceof Date) return value;
    if (typeof value !== 'string') return null;

    const dateOnly = value.match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if (dateOnly) {
        return new Date(Number(dateOnly[1]), Number(dateOnly[2]) - 1, Number(dateOnly[3]));
    }

    const parsed = new Date(value);
    return Number.isNaN(parsed.getTime()) ? null : parsed;
};

/**
 * A Date as a LOCAL 'YYYY-MM-DD'.
 *
 * The counterpart to toLocalDate above, and the missing half of the same
 * hazard: `date.toISOString().slice(0, 10)` formats in UTC, so anywhere west of
 * Greenwich it returns the previous day for as many hours as the offset. At
 * 02:00 in UTC-8 it reports yesterday.
 */
export function toIsoDate(value) {
    const date = toLocalDate(value) ?? new Date();
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
}

/** Today, in the viewer's own timezone. */
export function todayIso() {
    return toIsoDate(new Date());
}

const CURRENT_YEAR = new Date().getFullYear();

/**
 * 'Sat, Aug 2' — the year is appended only when it isn't the current one, so
 * the common case stays short without ever being ambiguous.
 */
export function formatDate(value, { weekday = true } = {}) {
    const date = toLocalDate(value);
    if (!date) return '';

    return date.toLocaleDateString(undefined, {
        weekday: weekday ? 'short' : undefined,
        month: 'short',
        day: 'numeric',
        year: date.getFullYear() === CURRENT_YEAR ? undefined : 'numeric',
    });
}

/** 'Aug 2 – 5', collapsing the repeated month, or 'Aug 30 – Sep 2' across one. */
export function formatDateRange(start, end) {
    const from = toLocalDate(start);
    const to = toLocalDate(end);
    if (!from) return '';
    if (!to) return formatDate(start);

    const sameYear = from.getFullYear() === to.getFullYear();
    const sameMonth = sameYear && from.getMonth() === to.getMonth();

    const left = from.toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
        year: from.getFullYear() === CURRENT_YEAR ? undefined : 'numeric',
    });
    const right = to.toLocaleDateString(undefined, {
        month: sameMonth ? undefined : 'short',
        day: 'numeric',
        year: to.getFullYear() === CURRENT_YEAR ? undefined : 'numeric',
    });

    return `${left} – ${right}`;
}

/** '14:30' or '14:30:00' -> '2:30 PM'. */
export function formatTime(value) {
    if (typeof value !== 'string') return '';
    const [h, m] = value.split(':');
    const hours = Number(h);
    if (Number.isNaN(hours)) return value;

    const date = new Date();
    date.setHours(hours, Number(m ?? 0), 0, 0);
    return date.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
}

/** Prices arrive as decimal strings ('120.50'), so coerce before formatting. */
export function formatMoney(value) {
    const amount = Number(value);
    if (Number.isNaN(amount)) return '';

    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
    }).format(amount);
}

/** 'Aug 2 at 2:30 PM' — the pairing used on every ticket and booking row. */
export function formatDateTime(dateValue, timeValue) {
    const date = formatDate(dateValue);
    const time = formatTime(timeValue);
    if (!date) return time;
    if (!time) return date;
    return `${date} at ${time}`;
}

/** Whole nights between two date-only strings; always at least 1. */
export function nightsBetween(start, end) {
    const from = toLocalDate(start);
    const to = toLocalDate(end);
    if (!from || !to) return 1;
    return Math.max(1, Math.round((to - from) / 86400000));
}
