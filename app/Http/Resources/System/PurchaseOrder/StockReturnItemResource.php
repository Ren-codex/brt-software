<?php

namespace App\Http\Resources\System\PurchaseOrder;

use App\Http\Resources\System\User\ViewResource;
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
            'replaced_quantity' => $this->replaced_quantity,
            'loss_quantity' => $this->loss_quantity,
            'remarks' => $this->remarks,
            'status' => $this->status,
            'received_by' => $this->receivedBy
                ? new ViewResource($this->receivedBy)
                : null,
            'received_at' => $this->received_at,
            'purchase_order_item' => $this->purchaseOrderItem
                ? new PurchaseOrderItemResource($this->purchaseOrderItem)
                : null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
