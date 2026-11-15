const rupees = new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' });

/**
 * The API keeps money in integer paise. Shows 123450 as "₹1,234.50".
 */
export function formatPaise(paise) {
    return rupees.format(paise / 100);
}

/**
 * Rupees as typed ("1,234.5") to integer paise (123450), without floating
 * point; null when it isn't an amount.
 */
export function toPaise(text) {
    const match = String(text ?? '')
        .trim()
        .replaceAll(',', '')
        .match(/^(\d{1,9})(?:\.(\d{1,2}))?$/);

    return match ? Number(match[1]) * 100 + Number((match[2] ?? '').padEnd(2, '0')) : null;
}

/**
 * Paise to rupees for an input field: 123450 as "1234.50".
 */
export function toRupeesText(paise) {
    return (paise / 100).toFixed(2);
}
