<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReceivedStockResource extends JsonResource
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
            'purchase_order' => $this->purchaseOrder,
            'supplier_id' => $this->supplier_id,
            'supplier' => $this->supplier,
            'received_date' => $this->received_date,
            'batch_code' => $this->batch_code,
            'items' => ReceivedItemResource::collection($this->items),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
