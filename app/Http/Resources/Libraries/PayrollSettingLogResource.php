<?php

namespace App\Http\Resources\Libraries;

use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayrollSettingLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'changed_data' => $this->changed_data,
            'updated_by' => $this->updatedBy ? new UserResource($this->updatedBy) : [],
            'created_at' => $this->created_at->format('F d, Y h:i A'),
        ];
    }
}
