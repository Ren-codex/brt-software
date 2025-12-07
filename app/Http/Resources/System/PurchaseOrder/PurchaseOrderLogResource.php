<?php

namespace App\Http\Resources\System\PurchaseOrder;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Libraries\ProductResource;

class PurchaseOrderLogResource extends JsonResource
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
            'user' => $this->user ? new \App\Http\Resources\System\User\ViewResource($this->user) : null,
            'action' => $this->action,
            'remarks' => $this->remarks,
            'created_at' => $this->created_at,
        ];
    }
}
