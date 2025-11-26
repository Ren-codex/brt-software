<?php

namespace App\Services\Modules;


use App\Models\SalesOrder;
use App\Http\Resources\Modules\SalesOrderResource;


class SalesOrderClass
{
    public function lists($request){
        $data = SalesOrderResource::collection(
            SalesOrder::when($request->keyword, function ($query,$keyword) {
                    $query->where('name', 'LIKE', "%{$keyword}%");
                })
            ->orderBy('created_at', 'DESC')
            ->paginate($request->count)
        );
        return $data;
    }

}
