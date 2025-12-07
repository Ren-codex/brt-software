<?php

namespace App\Http\Resources\System\PurchaseOrder;

use App\Http\Resources\System\User\ViewResource;
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
            'items' => $this->items ? PurchaseOrderItemResource::collection($this->items) : null,
            'logs' => $this->logs ? PurchaseOrderLogResource::collection($this->logs) : null,
            'created_by' => $this->created_by ? new ViewResource($this->created_by) : null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
