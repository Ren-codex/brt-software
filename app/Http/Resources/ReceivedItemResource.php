<?php

namespace App\Http\Resources;

use App\Http\Resources\Libraries\ProductResource;
use App\Http\Resources\System\PurchaseOrder\PurchaseOrderItemResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReceivedItemResource extends JsonResource
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
            'received_stock' => $this->receivedStock ? new ReceivedStockResource($this->receivedStock) : null,
            'product' => $this->product ? new ProductResource($this->product) : null,
            'purchase_order_item' => $this->purchaseOrderItem ? new PurchaseOrderItemResource($this->purchaseOrderItem) : null,
            'quantity' => $this->quantity,
            'unit_cost' => $this->unit_cost,
            'total_cost' => $this->total_cost,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
