<?php

namespace App\Http\Resources\Libraries;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->brand->name . ' ' . $this->pack_size . ' ' . $this->unit->name,
            'pack_size' => $this->pack_size,
            'brand' => $this->brand,
            'unit' => $this->unit,
            'price' => $this->price,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
