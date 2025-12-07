<?php

namespace App\Http\Resources;

use App\Http\Resources\Libraries\ListSupplierResource;
use App\Http\Resources\System\User\ViewResource;
use App\Http\Resources\System\PurchaseOrder\PurchaseOrderResource;
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
            'purchase_order' => $this->purchaseOrder ? new PurchaseOrderResource($this->purchaseOrder) : [],
            'supplier' => $this->supplier ? new ListSupplierResource($this->supplier) : [],
            'received_by' => $this->receivedBy ? new ViewResource($this->receivedBy) : null,
            'received_date' => $this->received_date,
            'batch_code' => $this->batch_code,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
