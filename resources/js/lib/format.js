import { formatPaise } from './money';

const dateFormat = new Intl.DateTimeFormat('en-IN', { day: 'numeric', month: 'short', year: 'numeric' });
const dateTimeFormat = new Intl.DateTimeFormat('en-IN', { day: 'numeric', month: 'short', year: 'numeric', hour: 'numeric', minute: '2-digit' });

/**
 * A calendar date ("2026-06-01") shown as "1 Jun 2026". Dates from the API
 * are calendar dates, so they are read as local dates, never shifted by
 * the timezone.
 */
export function formatDate(value) {
    if (!value) {
        return '';
    }

    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value);
    const date = match ? new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3])) : new Date(value);

    return Number.isNaN(date.getTime()) ? '' : dateFormat.format(date);
}

/**
 * An instant ("2026-06-01T04:30:00Z") shown in the browser's timezone, as
 * "1 Jun 2026, 10:00 am".
 */
export function formatDateTime(value) {
    if (!value) {
        return '';
    }

    const date = new Date(value);

    return Number.isNaN(date.getTime()) ? '' : dateTimeFormat.format(date);
}

/** Integer paise shown as rupees, e.g. 123450 as "₹1,234.50". */
export { formatPaise as formatRupees };
