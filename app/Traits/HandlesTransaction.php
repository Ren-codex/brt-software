<?php

namespace App\Traits;

trait HandlesTransaction
{
    public static function handleTransaction($callback){
        $data = '';
        $info = null;
        $status = false;

        try {
            $result = \DB::transaction($callback);
            // $status = ($result['status'] === 'error') ? false : true;
            $data = $result['data'];
            $info = $result['info'];
            $message = $result['message'];
            $status = $result['status'] ?? true;
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
        ];
    }
}
