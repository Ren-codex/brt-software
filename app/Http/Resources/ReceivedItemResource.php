<?php

namespace App\Http\Resources;

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
            'received_id' => $this->received_id,
            'product_id' => $this->product_id,
            'product' => $this->product,
            'quantity' => $this->quantity,
            'unit_cost' => $this->unit_cost,
            'total_cost' => $this->total_cost,
            'po_item_id' => $this->po_item_id,
            'purchase_order_item' => $this->purchaseOrderItem,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
