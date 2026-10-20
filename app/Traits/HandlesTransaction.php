<?php

namespace App\Traits;

use Illuminate\Database\DeadlockException;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

trait HandlesTransaction
{
    public static function handleTransaction($callback){
        $data = '';
        $info = null;
        $message = 'Error occured';
        $status = false;
        $errors = null;
        $receiptId = null;
        $password = null;

        try {
            $result = \DB::transaction($callback);
            $data = $result['data'] ?? null;
            $info = $result['info'] ?? null;
            $message = $result['message'] ?? 'Success';
            $status = $result['status'] ?? true;
            $receiptId = $result['receipt_id'] ?? null;
            $password = $result['password'] ?? null;
        } catch (ValidationException $e) {
            // Let Laravel turn this into field-level errors on the form rather
            // than flattening it into a generic failure banner.
            throw $e;
        } catch (DeadlockException $e) {
            report($e);
            $info = 'Someone else was saving the same record at that moment. Please try again.';
            $message = 'Error occured';
        } catch (QueryException $e) {
            // The raw SQL error means nothing to the person at the screen; it
            // goes to the log for whoever has to look into it.
            report($e);
            $info = self::describeQueryFailure($e);
            $message = 'Error occured';
        } catch (\Exception $e) {
            $info = 'An unexpected error occurred: ' . $e->getMessage();
            $message = 'Error occured';
        }

        return [
            'data' => ($data) ? $data : 'Nothing found.',
            'message' => $message,
            'info' => $info,
            'status' => $status,
            'errors' => $errors,
            'receipt_id' => $receiptId,
            'password' => $password,
        ];
    }

    /**
     * Say in plain words why the database refused a save. Reads both MySQL's
     * and SQLite's wording, since the test suite runs on SQLite.
     */
    protected static function describeQueryFailure(QueryException $e): string
    {
        $error = $e->getMessage();
        // contact_number -> "Contact number", position_id -> "Position".
        $field = fn (string $column) => ucfirst(str_replace('_', ' ', preg_replace('/_id$/', '', $column)));

        if (preg_match("/Column '([^']+)' cannot be null|NOT NULL constraint failed: \\w+\\.(\\w+)/", $error, $m)) {
            return $field($m[1] ?: $m[2]) . ' is required. Please fill it in and save again.';
        }

        if (preg_match("/Duplicate entry '[^']*' for key '(?:[^'.]+\\.)?([^']+)'|UNIQUE constraint failed: \\w+\\.(\\w+)/", $error, $m)) {
            $column = $m[2] ?? '';
            if ($column === '') {
                // MySQL names the index ("customers_email_unique"), not the column.
                $table = preg_match('/(?:into|update|from)\s+[`"]?(\w+)/i', $e->getSql(), $t) ? $t[1] : '';
                $column = preg_replace(['/_unique$/', '/^' . preg_quote($table, '/') . '_/'], '', $m[1]);
            }

            return 'Another record already uses this ' . strtolower($field($column)) . '. Please enter a different one.';
        }

        if (preg_match("/Data too long for column '([^']+)'/", $error, $m)) {
            return $field($m[1]) . ' is too long. Please shorten it and save again.';
        }

        if (preg_match("/Incorrect \\w+ value: .* for column (?:[^ ]+\\.)?'?`?([^'` ]+)/", $error, $m)) {
            return $field($m[1]) . ' has a value that is not valid. Please check it and save again.';
        }

        if (str_contains($error, 'Cannot delete or update a parent row')) {
            return 'This record is still used by other records, so it cannot be changed or deleted.';
        }

        if (str_contains($error, 'Cannot add or update a child row') || str_contains($error, 'FOREIGN KEY constraint failed')) {
            return 'Something selected on this form no longer exists. Please refresh the page and choose it again.';
        }

        return 'The changes could not be saved because of a database problem. Please try again, and contact the administrator if it keeps happening.';
    }
}
