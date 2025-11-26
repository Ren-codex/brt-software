<?php

namespace App\Services\Modules;


use App\Models\Customer;
use App\Http\Resources\Modules\CustomerResource;


class SalesOrderClass
{
    public function lists($request){
        $data = CustomerResource::collection(
            Customer::when($request->keyword, function ($query,$keyword) {
                    $query->where('name', 'LIKE', "%{$keyword}%")
                          ->orWhere('address', 'LIKE', "%{$keyword}%")
                          ->orWhere('contact_number', 'LIKE', "%{$keyword}%")
                          ->orWhere('email', 'LIKE', "%{$keyword}%")
                          ->orWhere('is_active', 'LIKE', "%{$keyword}%")
                          ->orWhere('is_regular', 'LIKE', "%{$keyword}%")
                          ->orWhere('is_blacklisted', 'LIKE', "%{$keyword}%")
                          ->orWhereHas('status', function($q) use ($keyword){
                              $q->where('name', 'LIKE', "%{$keyword}%");
                          });
                })
                ->orderBy('created_at', 'DESC')
                ->paginate($request->count)
        );
        return $data;
    }


}
