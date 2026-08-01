<?php

namespace App\Http\Resources\Libraries;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'           => $this->id,
            'code'         => $this->code,
            'name'         => $this->brand->name . ' ' . $this->weight . ' ' . $this->unit->name,
            'weight'    => $this->weight,
            'brand'        => $this->brand,
            'unit'         => $this->unit,
            'packaging'    => $this->packaging,
            'is_active'    => $this->is_active,
            'created_at'   => $this->created_at,
            'updated_at'   => $this->updated_at,
        ];
    }
}
