<?php

namespace App\Http\Resources\Modules;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SimpleEmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'fullname' => $this->fullname,
            'is_active' => $this->is_active,
            'position' => $this->position,
            'basic_salary' => $this->position->rate_per_day ?? null,
            'loans' => LoanResource::collection($this->loans->where('status', 'approved')),
        ];
    }
}
