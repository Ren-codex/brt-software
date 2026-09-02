<?php

use App\Support\RecordLock;
use Illuminate\Validation\ValidationException;

/** A stand-in for any model exposing a `status` relation. */
function lockable($status)
{
    return new class($status)
    {
        public $status;

        public function __construct($status)
        {
            $this->status = is_string($status) || is_null($status)
                ? $status
                : (object) $status;
        }
    };
}

it('locks records whose status slug is terminal', function (string $slug) {
    expect(RecordLock::isLocked(lockable(['slug' => $slug])))->toBeTrue();
})->with(RecordLock::TERMINAL_STATUS_SLUGS);

it('leaves open records editable', function (string $slug) {
    expect(RecordLock::isLocked(lockable(['slug' => $slug])))->toBeFalse();
})->with(['pending', 'draft', 'open', 'for-payment', 'partially-paid', 'unpaid', 'approval']);

it('falls back to the status name when no slug is present', function () {
    expect(RecordLock::isLocked(lockable(['name' => 'Completed'])))->toBeTrue()
        ->and(RecordLock::isLocked(lockable(['name' => 'Pending'])))->toBeFalse();
});

it('normalises multi-word status names into slugs', function () {
    expect(RecordLock::statusSlug(lockable(['name' => 'Partially Paid'])))->toBe('partially-paid');
});

it('handles a status stored as a plain string on the record', function () {
    expect(RecordLock::isLocked(lockable('voided')))->toBeTrue()
        ->and(RecordLock::isLocked(lockable('draft')))->toBeFalse();
});

it('treats a missing status as editable rather than locked', function () {
    expect(RecordLock::isLocked(lockable(null)))->toBeFalse();
});

it('throws a validation error naming the status when asserting on a locked record', function () {
    expect(fn () => RecordLock::assertEditable(lockable(['name' => 'Completed']), 'This purchase order', 'edited'))
        ->toThrow(ValidationException::class);

    try {
        RecordLock::assertEditable(lockable(['name' => 'Completed']), 'This purchase order', 'edited');
    } catch (ValidationException $e) {
        expect($e->errors()['status'][0])
            ->toBe('This purchase order is already marked as Completed and can no longer be edited.');
    }
});

it('reports the error under the field it was given', function () {
    try {
        RecordLock::assertEditable(lockable(['name' => 'Voided']), 'This purchase order', 'deleted', 'delete');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('delete');
    }
});

it('passes silently for an editable record', function () {
    RecordLock::assertEditable(lockable(['slug' => 'pending']), 'This purchase order');
    expect(true)->toBeTrue();
});
