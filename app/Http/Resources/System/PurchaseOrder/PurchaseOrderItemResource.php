<?php

namespace App\Http\Resources\System\PurchaseOrder;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Libraries\ProductResource;

class PurchaseOrderItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'po_id' => $this->po_id,
            'quantity' => $this->quantity,
            'unit_cost' => $this->unit_cost,
            'total_cost' => $this->total_cost,
            'status' => $this->status,
            'product' => $this->product ? new ProductResource($this->product) : null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'received_quantity' => $this->received_quantity,
            'batches' => $this->receivedItems
                ? $this->receivedItems
                    ->flatMap(function ($receivedItem) {
                        return $receivedItem->inventoryStocks->map(function ($stock) use ($receivedItem) {
                            return [
                                'batch_code' => $stock->batch_code,
                                'quantity' => (int) $stock->quantity,
                                'received_quantity' => (float) $receivedItem->quantity,
                                'received_id' => $receivedItem->received_id,
                                'received_date' => optional($receivedItem->receivedStock)->received_date,
                            ];
                        });
                    })
                    ->values()
                : [],
        ];
    }
}
