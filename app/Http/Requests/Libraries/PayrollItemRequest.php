<?php

namespace App\Http\Requests\Libraries;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PayrollItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('payroll_item') ?? $this->route('id');

        return [
            'name' => ['required','string','max:255',
                Rule::unique('list_payroll_items', 'name')->ignore($id),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'type' => ['required', Rule::in(['earning', 'deduction'])],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'This field is required',
            'type.required' => 'This field is required',
            'type.in' => 'Type must be earning or deduction',
        ];
    }
}
