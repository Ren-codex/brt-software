<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PayrollRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'pay_period_start' => 'required|date',
            'pay_period_end' => 'required|date',
            'payroll_template_id' => 'required|exists:payroll_templates,id',
            'items' => 'required|array|min:1',
            'items.*.employee_id' => 'required|exists:employees,id',
            'items.*.basic_salary' => 'required|numeric|min:0',
            'items.*.overtime_hours' => 'nullable|numeric|min:0',
            'items.*.overtime_rate' => 'nullable|numeric|min:0',
            'items.*.deductions' => 'nullable|numeric|min:0',
            'items.*.total_days' => 'nullable|integer|min:0',
            'items.*.net_salary' => 'required|numeric|min:0',
            'total_amount' => 'required|numeric|min:0',
        ];
    }
}
