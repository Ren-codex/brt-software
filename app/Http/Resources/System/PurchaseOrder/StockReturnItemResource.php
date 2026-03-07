<?php

namespace App\Http\Resources\System\PurchaseOrder;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockReturnItemResource extends JsonResource
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
            'stock_return_id' => $this->stock_return_id,
            'po_item_id' => $this->po_item_id,
            'quantity' => $this->quantity,
            'returned_quantity' => $this->returned_quantity,
            'remarks' => $this->remarks,
            'status' => $this->status,
            'purchase_order_item' => $this->purchaseOrderItem
                ? new PurchaseOrderItemResource($this->purchaseOrderItem)
                : null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
