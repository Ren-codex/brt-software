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
            // Two faults in one line: the employee was not checked for before
            // being reached through, which took the whole payroll list down
            // with a 500 whenever a log was written by a user who has no
            // employee record — and every payroll has a log. The attribute was
            // also misspelt (the accessor is `fullname`), so even where an
            // employee existed this showed nothing.
            'actioned_by' => $this->actionedBy?->employee?->fullname ?? $this->actionedBy?->username,
            'created_at' => $this->created_at ? $this->created_at->toDateTimeString() : null,
            'remarks' => $this->remarks,
        ];
    }
}
