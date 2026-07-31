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

        try {
            $result = \DB::transaction($callback);
            $data = $result['data'];
            $info = $result['info'];
            $message = $result['message'];
            $status = $result['status'] ?? true;
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
        ];
    }
}
