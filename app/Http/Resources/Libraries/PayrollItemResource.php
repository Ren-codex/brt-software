<?php

namespace App\Http\Resources\Libraries;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayrollItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payroll_id' => $this->payroll_id,
            'employee_id' => $this->employee_id,
            'employee_name' => $this->employee ? $this->employee->fullname : null,
            'basic_salary' => $this->basic_salary,
            'deductions' => $this->deductions,
            'earnings' => $this->earnings,
            'total_earnings' => $this->total_earnings,
            'total_deductions' => $this->total_deductions,
            'net_salary' => $this->net_salary,
            'total_days' => $this->total_days,
            'loans' => $this->loans,
        ];
    }
}
