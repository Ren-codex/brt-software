<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class InventoryStockResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'batch_code' => $this->batch_code,
            'quantity' => $this->quantity,
            'received_item' => $this->receivedItem ? new ReceivedItemResource($this->receivedItem) : null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'inventory_adjustments' => $this->inventoryAdjustments ? InventoryAdjustmentResource::collection($this->inventoryAdjustments) : [],
            'retail_price' => $this->retail_price,
            'wholesale_price' => $this->wholesale_price,
            'expiration_date' => $this->expiration_date,
        ];
    }
}
