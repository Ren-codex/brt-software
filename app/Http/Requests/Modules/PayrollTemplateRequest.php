<?php

namespace App\Http\Requests\Modules;

use Illuminate\Foundation\Http\FormRequest;

class PayrollTemplateRequest extends FormRequest
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
        $rules = [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ];

        if ($this->isMethod('post')) {
            $rules['employee_ids'] = 'required|array';
        } else {
            $rules['employee_ids'] = 'nullable|array';
        }

        $rules['employee_ids.*'] = 'integer|exists:employees,id';

        return $rules;
    }
}
