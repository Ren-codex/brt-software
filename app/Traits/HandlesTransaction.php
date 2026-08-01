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
            $info = 'Transaction failed due to deadlock: ' . $e->getMessage();
            $message = 'Error occured';
        } catch (QueryException $e) {
            $info = 'Transaction failed: ' . $e->getMessage();
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
}
