<?php

namespace App\Services\Modules;

use App\Http\Resources\InventoryStockResource;
use App\Http\Resources\ReceivedStockResource;
use App\Models\InventoryStocks;

class InventoryStockClass
{
    public function lists($request)
    {
        $data = InventoryStockResource::collection(
            InventoryStocks::with('receivedItem.product')
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
        $inventoryStock = InventoryStocks::findOrFail($id);
        return new InventoryStockResource($inventoryStock);
    }
}
