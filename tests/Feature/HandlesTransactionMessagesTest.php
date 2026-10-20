<?php

use App\Models\Customer;
use App\Models\ListRole;
use App\Traits\HandlesTransaction;
use Illuminate\Database\QueryException;

// A database refusal reaches the person at the screen as plain words, never
// as the raw SQL error.
function failedSave(callable $write): array
{
    $runner = new class { use HandlesTransaction; };

    return $runner::handleTransaction($write);
}

test('a missing required column is named in plain words', function () {
    $result = failedSave(fn () => Customer::create(['address' => 'Cebu']));

    expect($result['status'])->toBeFalse()
        ->and($result['info'])->toBe('Name is required. Please fill it in and save again.')
        ->and($result['info'])->not->toContain('SQLSTATE');
});

test('a duplicate value is named in plain words', function () {
    ListRole::create(['name' => 'Dup Test Role', 'type' => 'role', 'definition' => 't', 'is_active' => true]);

    $result = failedSave(fn () => ListRole::create(['name' => 'Dup Test Role', 'type' => 'role', 'definition' => 't', 'is_active' => true]));

    expect($result['info'])->toBe('Another record already uses this name. Please enter a different one.');
});

test('the MySQL wording is read too', function () {
    $describe = fn (string $sql, string $error) => (new class { use HandlesTransaction; public static function say($e) { return self::describeQueryFailure($e); } })::say(
        new QueryException('mysql', $sql, [], new \PDOException($error))
    );

    expect($describe('update `customers` set `contact_number` = ?', "SQLSTATE[23000]: Integrity constraint violation: 1048 Column 'contact_number' cannot be null"))
        ->toBe('Contact number is required. Please fill it in and save again.')
        ->and($describe('insert into `customers` (`email`) values (?)', "SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry 'a@b.com' for key 'customers.customers_email_unique'"))
        ->toBe('Another record already uses this email. Please enter a different one.')
        ->and($describe('update `customers` set `name` = ?', "SQLSTATE[22001]: String data, right truncated: 1406 Data too long for column 'name' at row 1"))
        ->toBe('Name is too long. Please shorten it and save again.')
        ->and($describe('delete from `customers`', 'SQLSTATE[23000]: Integrity constraint violation: 1451 Cannot delete or update a parent row: a foreign key constraint fails'))
        ->toBe('This record is still used by other records, so it cannot be changed or deleted.')
        ->and($describe('update `employees` set `position_id` = ?', 'SQLSTATE[23000]: Integrity constraint violation: 1452 Cannot add or update a child row: a foreign key constraint fails'))
        ->toBe('Something selected on this form no longer exists. Please refresh the page and choose it again.');
});
