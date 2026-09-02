<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

/**
 * Whether a business document has reached a status that closes it to further
 * editing. Once a document is completed, approved, voided (or any of the other
 * end-of-line statuses below) its numbers have already been posted to the
 * ledger, matched against stock, or paid out — editing or deleting it would
 * silently rewrite history.
 *
 * Mirrored in resources/js/Shared/recordLock.js, which hides the buttons. This
 * class is what actually refuses, so a direct API call cannot bypass the UI.
 */
class RecordLock
{
    public const TERMINAL_STATUS_SLUGS = [
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

    /** Normalise whatever the model exposes ("Partially Paid", "partially-paid") to a slug. */
    public static function statusSlug($record): string
    {
        $status = $record->status ?? null;

        if (is_null($status)) {
            return '';
        }

        $raw = is_string($status)
            ? $status
            : ($status->slug ?? $status->name ?? '');

        return str_replace(' ', '-', strtolower(trim((string) $raw)));
    }

    public static function isLocked($record): bool
    {
        return in_array(self::statusSlug($record), self::TERMINAL_STATUS_SLUGS, true);
    }

    /**
     * Guard an edit or delete. $document names the record in the error message
     * ("Purchase order"), $action the thing being refused ("edited", "deleted").
     */
    public static function assertEditable($record, string $document, string $action = 'edited', string $field = 'status'): void
    {
        if (! self::isLocked($record)) {
            return;
        }

        $label = is_string($record->status ?? null)
            ? $record->status
            : ($record->status->name ?? self::statusSlug($record));

        throw ValidationException::withMessages([
            $field => ["{$document} is already marked as {$label} and can no longer be {$action}."],
        ]);
    }
}
