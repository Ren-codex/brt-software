<?php

namespace App\Services\Modules;

use App\Http\Resources\InventoryStockResource;
use App\Http\Resources\ReceivedStockResource;
use App\Models\InventoryStocks;
use Illuminate\Support\Facades\DB;
use App\Models\InventoryAdjustment;
use Illuminate\Support\Facades\Auth;

class InventoryStockClass
{
    public function lists($request)
    {
        $data = InventoryStockResource::collection(
            InventoryStocks::with('receivedItem.product', 'inventoryAdjustments')
                ->when($request->keyword, function ($query, $keyword) {
                    $query->whereHas('receivedItem.product', function ($q) use ($keyword) {
                        $q->where('name', 'LIKE', "%{$keyword}%");
                    });
                })
                ->orderBy('created_at', 'DESC')
                ->paginate($request->count)
        );
        return $data;
    }

    public function view($id)
    {
        $inventoryStock = InventoryStocks::with('inventoryAdjustments')->findOrFail($id);
        return new InventoryStockResource($inventoryStock);
    }

    public function update($request)
    {
        try {
            DB::beginTransaction();

            $inventoryStock = InventoryStocks::findOrFail($request->inventory_stocks_id);

            $is_retail_price = $request->retail_price != $inventoryStock->retail_price ? true : false;
            $is_wholesale_price = $request->wholesale_price != $inventoryStock->wholesale_price ? true : false;

            if($is_retail_price == true){
                InventoryAdjustment::create([
                    'inventory_stocks_id' =>  $request->inventory_stocks_id,
                    'new_quantity' =>  $request->retail_price,
                    'previous_quantity' =>  $inventoryStock->retail_price,
                    'reason' =>  $request->reason,
                    'type' =>  'update retail price',
                    'adjustment_date' =>  now(),
                    'adjusted_by_id' =>  Auth::id(),
                ]);
            }

            if($is_wholesale_price == true){
                InventoryAdjustment::create([
                    'inventory_stocks_id' =>  $request->inventory_stocks_id,
                    'new_quantity' =>  $request->wholesale_price,
                    'previous_quantity' =>  $inventoryStock->wholesale_price,
                    'reason' =>  $request->reason,
                    'type' =>  'update wholesale price',
                    'adjustment_date' =>  now(),
                    'adjusted_by_id' =>  Auth::id(),
                ]);
            }
            
            $inventoryStock->update([
                'retail_price' => $request->retail_price,
                'wholesale_price' => $request->wholesale_price,
            ]);


            DB::commit();

            return new InventoryStockResource($inventoryStock);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
