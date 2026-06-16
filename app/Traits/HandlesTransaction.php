<?php

namespace App\Traits;

use Illuminate\Database\QueryException;
use Illuminate\Database\DeadlockException;
use Illuminate\Validation\ValidationException;

trait HandlesTransaction
{
    public static function handleTransaction($callback){
        $data = '';
        $info = null;
        $status = false;
        $errors = null;
        $receiptId = null;
        $password = null;

        try {
            $result = \DB::transaction($callback);
            // $status = ($result['status'] === 'error') ? false : true;
            $data = $result['data'] ?? null;
            $info = $result['info'] ?? null;
            $message = $result['message'] ?? 'Success';
            $status = $result['status'] ?? true;
            $receiptId = $result['receipt_id'] ?? null;
            $password = $result['password'] ?? null;
        } catch (ValidationException $e) {
            $errors = $e->errors();
            $info = collect($errors)->flatten()->first() ?? 'Validation failed.';
            $message = 'Validation failed';
        } catch (QueryException $e) {
            $info = 'Transaction failed: ' . $e->getMessage();
            $message = 'Error occured';
        } catch (DeadlockException $e) {
            $info = 'Transaction failed due to deadlock: ' . $e->getMessage();
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
