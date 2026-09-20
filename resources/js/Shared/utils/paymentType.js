/**
 * How a sale settles: at the counter, at the door, or on a term granted to the
 * customer.
 *
 * This is the sale's type, not the method the money arrived by. A cash sale
 * paid by bank transfer, cheque or a split of several is still a counter sale
 * to anyone scanning a list, so the method sits after the dot rather than
 * replacing the category.
 *
 * Shared so the same order reads the same way on every screen.
 */
const CREDIT_MODES = ['credit', 'credit sales'];
const COUNTER_METHODS = ['bank transfer', 'check', 'cheque', 'gcash'];

const normalize = (mode) => String(mode || 'cash').trim().toLowerCase();

export function paymentTone(mode) {
    const value = normalize(mode);
    if (CREDIT_MODES.includes(value)) return 'is-credit';
    if (value === 'cod') return 'is-cod';
    return 'is-cash';
}

export function paymentLabel(mode) {
    const value = normalize(mode);
    if (CREDIT_MODES.includes(value)) return 'Credit';
    if (value === 'cod') return 'COD';
    if (value === 'split') return 'Cash · Split';
    if (COUNTER_METHODS.includes(value)) return `Cash · ${String(mode).trim()}`;
    return 'Cash';
}
