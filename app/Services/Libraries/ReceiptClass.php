<?php

namespace App\Services\Libraries;


use App\Models\Receipt;
use App\Http\Resources\Libraries\ReceiptResource;


class ReceiptClass
{
    public function lists($request){
        $data = ReceiptResource::collection(
            Receipt::when($request->keyword, function ($query,$keyword) {
                        $query->where('receipt_number', 'LIKE', "%{$keyword}%")
                                ->orWhere('payment_mode', 'LIKE', "%{$keyword}%");
                })->when($request->status_id, function ($query,$status_id) {
                    $query->where('status_id', $status_id);
                })
                ->orderBy('created_at', 'DESC')
                ->paginate($request->count)
        );
        return $data;
    }
}
