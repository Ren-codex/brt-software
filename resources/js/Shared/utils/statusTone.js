/**
 * Which of five tones a status belongs to.
 *
 * Colour used to be stored per status, which meant it carried no meaning: Paid
 * and Closed were black, Pending and Unpaid were green, and seven statuses
 * shared one amber — including Approved and Disapproved. Nobody could scan a
 * list and see what needed chasing.
 *
 * Tone comes from what a status means instead, so the same meaning looks the
 * same on every screen.
 */
const TONES = {
    // Done, and it went well.
    settled: [
        'paid', 'closed', 'liquidated', 'completed',
        'remitted', 'released', 'approved', 'allowance-applied',
    ],
    // Moving as expected. Nobody owes an action yet.
    progress: [
        'pending', 'draft', 'approval', 'sales-return-approval',
        'receive', 'adjusted', 'partial', 'replaced',
    ],
    // Somebody has to do something. 'for-payment' and 'partially-paid' sit here
    // rather than under progress: both name money still to be collected, and a
    // collector scanning the list needs them to stand out, not blend in.
    attention: [
        'unpaid', 'for-payment', 'partially-paid',
        'partially-returned', 'sales-returned',
    ],
    // Went wrong, or the money is at risk.
    problem: [
        'overdue', 'voided', 'disapproved', 'loss',
    ],
    // Closed without value. Nothing to chase.
    inactive: [
        'cancelled',
    ],
};

const BY_SLUG = Object.entries(TONES).reduce((map, [tone, slugs]) => {
    slugs.forEach((slug) => { map[slug] = tone; });
    return map;
}, {});

/**
 * Accepts a status record, or a bare slug.
 *
 * An unrecognised status falls back to `progress` rather than a grey: a new
 * status is far more likely to be a step in a workflow than something closed
 * and forgotten, and greying it out would hide it in exactly the list someone
 * is scanning.
 */
export function statusTone(status) {
    const slug = typeof status === 'string' ? status : status?.slug;

    if (!slug) return 'progress';

    return BY_SLUG[String(slug).toLowerCase()] ?? 'progress';
}

export default statusTone;
