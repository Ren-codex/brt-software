<?php

namespace App\Services\Modules;


use App\Models\SalesOrder;
use App\Http\Resources\Modules\SalesOrderResource;


class SalesOrderClass
{
    public function lists($request){
        $data = SalesOrderResource::collection(
            SalesOrder::when($request->keyword, function ($query,$keyword) {
                    $query->where('name', 'LIKE', "%{$keyword}%")
                          ->orWhere('name', 'LIKE', "%{$keyword}%")
                          ->orWhere('address', 'LIKE', "%{$keyword}%")
                          ->orWhere('contact_number', 'LIKE', "%{$keyword}%")   
                          ->orWhere('email', 'LIKE', "%{$keyword}%")
                          ->orWhereHas('status', function($q) use ($keyword){
                              $q->where('name', 'LIKE', "%{$keyword}%");
                          });
                })
                ->orderBy('created_at', 'DESC')
                ->paginate($request->count)
        );
        return $data;
    }


    public function save($request){
        $data = SalesOrder::create([
            'customer_id' => $request->customer_id,
            'payment_mode' => $request->payment_mode,
            'order_date' => $request->order_date,
            'amount' => $request->amount,
            'added_by_id' => auth()->id(),
        ]);

        return [
            'data' => new CustomerResource($data),
            'message' => 'Customer saved successfully!',
            'info' => "You've successfully saved the customer"
        ];
    }

}
