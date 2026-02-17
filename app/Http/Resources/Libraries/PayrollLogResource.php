<?php

namespace App\Http\Resources\Libraries;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\UserResource;
use App\Http\Resources\Modules\PayrollTemplateResource;

class PayrollLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payroll_id' => $this->payroll_id,
            'action' => $this->action,
            'actioned_by' => $this->actioned_by ? $this->actioned_by->fullname : null,
            'created_at' => $this->created_at ? $this->created_at->toDateTimeString() : null,
            'remarks' => $this->remarks,
        ];
    }
}
