<?php

namespace App\Http\Resources\System\PurchaseOrder;

use App\Http\Resources\System\User\ViewResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockReturnResource extends JsonResource
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
            'reason' => $this->reason,
            'status' => $this->status,
            'purchase_order' => $this->purchaseOrder
                ? new PurchaseOrderResource($this->purchaseOrder)
                : null,
            'items' => $this->items
                ? StockReturnItemResource::collection($this->items)
                : null,
            'created_by' => $this->createdBy
                ? new ViewResource($this->createdBy)
                : null,
            'approved_by' => $this->approvedBy
                ? new ViewResource($this->approvedBy)
                : null,
            'approved_at' => $this->approved_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
