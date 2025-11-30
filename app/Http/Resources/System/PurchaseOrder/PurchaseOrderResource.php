<?php

namespace App\Http\Resources\System\PurchaseOrder;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderResource extends JsonResource
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
            'po_number' => $this->po_number,
            'po_date' => $this->po_date,
            'total_amount' => $this->total_amount,
            'status' => $this->status,
            'supplier' => $this->supplier,
            'items' => $this->items,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
