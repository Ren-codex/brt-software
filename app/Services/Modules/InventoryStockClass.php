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
            InventoryStocks::with('receivedItem.product', 'product', 'inventoryAdjustments', 'weightLosses')
                ->when($request->keyword, function ($query, $keyword) {
                    $query->where(function ($q) use ($keyword) {
                        $q->whereHas('receivedItem.product', function ($q2) use ($keyword) {
                            $q2->where('name', 'LIKE', "%{$keyword}%");
                        })->orWhereHas('product', function ($q2) use ($keyword) {
                            $q2->where('name', 'LIKE', "%{$keyword}%");
                        })->orWhere('batch_code', 'LIKE', "%{$keyword}%");
                    });
                })
                ->when($request->exclude_converted, fn ($q) => $q->whereNull('conversion_id'))
                ->orderBy('created_at', 'DESC')
                ->paginate($request->count)
        );
        return $data;
    }

    public function view($id)
    {
        $inventoryStock = InventoryStocks::with([
            'inventoryAdjustments.adjustedBy.employee',
            'receivedItem.product',
            'product',
            'conversion.sourceStock.receivedItem.product',
            'conversion.convertedBy.employee',
            'conversionsOut.outputStock.receivedItem.product',
            'conversionsOut.outputStock.product',
            'conversionsOut.convertedBy.employee',
            'weightLosses.recordedBy.employee',
            'weightLosses.convertedBy.employee',
        ])->findOrFail($id);
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
                    'previous_quantity' =>  $inventoryStock->retail_price || 0,
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
                    'previous_quantity' =>  $inventoryStock->wholesale_price || 0,
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

    public function updateSettings($request, $id)
    {
        $stock = InventoryStocks::findOrFail($id);

        $changes = [];

        if ($request->batch_code && $request->batch_code !== $stock->batch_code) {
            $changes[] = 'Batch code changed from ' . $stock->batch_code . ' to ' . $request->batch_code;
        }
        if ($request->expiration_date !== $stock->expiration_date) {
            $changes[] = 'Expiration date updated';
        }
        if ($request->unit_cost != $stock->unit_cost) {
            $changes[] = 'Unit cost updated from ' . $stock->unit_cost . ' to ' . $request->unit_cost;
        }
        if ((bool) $request->is_archived !== (bool) $stock->is_archived) {
            $changes[] = $request->is_archived ? 'Batch archived' : 'Batch unarchived';
        }
        if ((bool) $request->is_expired !== (bool) $stock->is_expired) {
            $changes[] = $request->is_expired ? 'Marked as expired' : 'Marked as not expired';
        }

        $stock->update([
            'batch_code'      => $request->batch_code      ?? $stock->batch_code,
            'expiration_date' => $request->expiration_date ?? null,
            'unit_cost'       => $request->unit_cost       ?? $stock->unit_cost,
            'notes'           => $request->notes           ?? null,
            'is_archived'     => (bool) ($request->is_archived ?? false),
            'is_expired'      => (bool) ($request->is_expired  ?? false),
        ]);

        if (!empty($changes)) {
            InventoryAdjustment::create([
                'inventory_stocks_id' => $stock->id,
                'previous_quantity'   => $stock->quantity,
                'new_quantity'        => $stock->quantity,
                'reason'              => implode('; ', $changes),
                'type'                => 'settings_update',
                'adjustment_date'     => now()->format('Y-m-d'),
                'adjusted_by_id'      => Auth::id(),
            ]);
        }

        return [
            'data'    => new InventoryStockResource($stock),
            'message' => 'Stock settings updated successfully.',
            'info'    => empty($changes) ? 'No changes detected.' : implode('; ', $changes),
            'status'  => true,
        ];
    }
}
