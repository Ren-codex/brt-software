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
            'stock_return_logs' => $this->logs
                ? $this->logs
                    ->sortByDesc('created_at')
                    ->values()
                    ->map(function ($log) {
                        return [
                            'id' => $log->id,
                            'action' => $log->action,
                            'remarks' => $log->remarks,
                            'user' => $log->user
                                ? new ViewResource($log->user)
                                : null,
                            'created_at' => $log->created_at,
                            'updated_at' => $log->updated_at,
                        ];
                    })
                : [],
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
