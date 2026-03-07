<?php

namespace App\Http\Resources\Modules;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Modules\SimpleEmployeeResource;

class PayrollTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'is_active' => $this->is_active,
            'created_by' => $this->created_by,
            'employees' => SimpleEmployeeResource::collection($this->employees),
            'created_at' => $this->created_at->format('F d, Y'),
            'updated_at' => $this->updated_at->format('F d, Y')
        ];
    }
}

