<?php

namespace App\Http\Resources\Libraries;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'pack_size' => $this->pack_size,
            'brand' => $this->brand ? new ListBrandResource($this->brand) : null,
            'unit' => $this->unit ? new ListUnitResource($this->unit) : null,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
