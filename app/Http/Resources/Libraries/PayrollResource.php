<?php

namespace App\Http\Resources\Libraries;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\UserResource;
use App\Http\Resources\Modules\PayrollTemplateResource;

class PayrollResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payroll_no' => $this->payroll_no,
            'pay_period_start' => $this->pay_period_start,
            'pay_period_end' => $this->pay_period_end,
            'total_amount' => $this->total_amount,
            'status' => $this->status,
            'payroll_name' => $this->template ? $this->template->name : null,
            'created_by' => $this->creator ? $this->creator->employee->fullname : null,
            'created_by_id' => $this->created_by,
            'approved_by_id' => $this->approved_by_id,
            'approved_at' => $this->approved_at?->format('F d, Y h:i A'),
            'approved_by' => $this->approvedBy?->employee?->fullname ?? $this->approvedBy?->username,
            'payroll_template_id' => $this->payroll_template_id,
            'payroll_items' => $this->items ? PayrollItemResource::collection($this->items) : [],
            'logs' => $this->logs ? PayrollLogResource::collection($this->logs) : [],
            'payroll_period' => $this->pay_period_start->format('F j') . '-' . $this->pay_period_end->format('j, Y')
        ];
    }
}
