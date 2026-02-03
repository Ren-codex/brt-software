<?php

namespace App\Http\Requests\Modules;

use Illuminate\Foundation\Http\FormRequest;

class AddEmployeesRequest extends FormRequest
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
            'payroll_template_id' => 'required|integer|exists:payroll_templates,id',
            'employee_ids' => 'required|array|min:1',
            'employee_ids.*' => 'integer|exists:employees,id',
        ];
    }
}
