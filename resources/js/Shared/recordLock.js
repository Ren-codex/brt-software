/**
 * Whether a business document has reached a status that closes it to further
 * editing. Once a document is completed, approved, voided (or any of the other
 * end-of-line statuses below) its numbers have already been posted to the
 * ledger, matched against stock, or paid out — editing or deleting it would
 * silently rewrite history.
 *
 * The same list lives server-side in App\Support\RecordLock. Keep them in step:
 * hiding a button is only a courtesy, the service class is what actually refuses.
 */
export const TERMINAL_STATUS_SLUGS = [
    'completed',
    'closed',
    'cancelled',
    'voided',
    'approved',
    'liquidated',
    'disapproved',
    'released',
    'remitted',
    'replaced',
    'paid',
];

const TERMINAL = new Set(TERMINAL_STATUS_SLUGS);

/**
 * Records reach us in a few shapes: a `status` relation with a slug, the same
 * relation with only a name, or a bare string (the Accounting screens store the
 * status on the row itself). Normalise all three to a slug.
 */
export function statusSlug(record) {
    const status = record?.status;
    if (!status) return '';

    const raw = typeof status === 'string' ? status : status.slug || status.name || '';

    return String(raw).trim().toLowerCase().replace(/\s+/g, '-');
}

export function isLocked(record) {
    return TERMINAL.has(statusSlug(record));
}

/** Convenience for templates: `v-if="isEditable(row)"`. */
export function isEditable(record) {
    return !isLocked(record);
}

/**
 * Drop into a component's `mixins` to get `isLocked` / `isEditable` in templates
 * without importing them one by one.
 */
export const recordLockMixin = {
    methods: { isLocked, isEditable, statusSlug },
};
