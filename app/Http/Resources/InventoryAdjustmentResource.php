<?php

namespace App\Http\Resources;

use App\Http\Resources\System\User\ViewResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryAdjustmentResource extends JsonResource
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
            'inventory_stocks_id' => $this->inventory_stocks_id,
            'new_quantity' => $this->new_quantity,
            'previous_quantity' => $this->previous_quantity,
            'reason' => $this->reason,
            'adjustment_date' => $this->adjustment_date,
            'received_by' => $this->adjustedBy ? new ViewResource($this->adjustedBy) : null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
