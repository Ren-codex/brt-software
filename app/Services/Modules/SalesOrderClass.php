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
        $so_number = SalesOrder::generateSONumber();
        $data = SalesOrder::create([
            'so_number' => $so_number,
            'received_id' => $request->batch_code,
            'customer_id' => $request->customer_id,
            'payment_mode' => $request->payment_mode,
            'order_date' => $request->order_date,
            'added_by_id' => auth()->id(),
            'status_id' => 1, // Default to 'Pending' status
        ]);


        foreach($request->items as $item){
            $data->items()->create([
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'unit_cost' => $item['unit_cost'],
            ]);
        }

        return [
            'data' => new SalesOrderResource($data),
            'message' => 'Sales Order saved successfully!',
            'info' => "You've successfully saved the Sales Order"
        ];
    }


    public function update($request){
        $data = SalesOrder::findOrFail($request->id);
        $data->update([
            'received_id' => $request->batch_code,
            'customer_id' => $request->customer_id,
            'payment_mode' => $request->payment_mode,
            'order_date' => $request->order_date,
        ]);
        // Clear existing items
        $data->items()->delete();
        // Add new items
        foreach($request->items as $item){
            $data->items()->create([
                'brand_id' => $item['brand_id'],
                'unit_id' => $item['unit_id'],
                'quantity' => $item['quantity'],
                'unit_cost' => $item['unit_cost'],
            ]);
        }

        return [
            'data' => new SalesOrderResource($data),
            'message' => 'Sales Order saved successfully!',
            'info' => "You've successfully saved the Sales Order"
        ];
    }

    public function cancel($id){
        $data = SalesOrder::findOrFail($id);
        $data->update([
            'status_id' => 2, //set to cancelled
        ]);

        return [
            'data' => $data,
            'message' => 'Sales Order deleted was successful!',
            'info' => "You've successfully deleted the Sales Order"
        ];
    }

}
