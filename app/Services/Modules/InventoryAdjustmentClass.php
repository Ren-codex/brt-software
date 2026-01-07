<?php

namespace App\Services\Modules;

use App\Http\Resources\InventoryAdjustmentResource;
use App\Models\InventoryAdjustment;
use App\Models\InventoryStocks;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InventoryAdjustmentClass
{
    public function save($request)
    {
        try {
            DB::beginTransaction();

            $inventoryStock = InventoryStocks::findOrFail($request->inventory_stocks_id);
            $inventoryStock->quantity = $request->new_quantity;
            $inventoryStock->update();
                
            $data = InventoryAdjustment::create([
                'inventory_stocks_id' =>  $request->inventory_stocks_id,
                'new_quantity' =>  $request->new_quantity,
                'previous_quantity' =>  $request->previous_quantity,
                'reason' =>  $request->reason,
                'type' =>  $request->type,
                'adjustment_date' =>  now(),
                'adjusted_by_id' =>  Auth::id(),
            ]);

            DB::commit();

            return [
                'data' => new InventoryAdjustmentResource($data),
                'message' => 'Inventory adjustment saved successful!', 
                'info' => "You've successfully saved the adjustment"
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
