<?php

namespace App\Http\Resources\Libraries;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayrollSettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'field_name' => $this->field_name,
            'formula' => $this->formula,
            'value' => $this->value,
            'is_active' => $this->is_active,
            'logs' => $this->logs ? PayrollSettingLogResource::collection($this->logs) : [],
        ];
    }
}
