<?php

namespace App\Http\Requests\Libraries;

use Illuminate\Foundation\Http\FormRequest;

class FundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->input('id');
        return [
            'name'          => 'required|string|max:255',
            'gl_code'       => 'required|string|max:50|unique:petty_cash_funds,gl_code' . ($id ? ",{$id}" : ''),
            'weekly_budget'         => 'nullable|numeric|min:0',
            'low_balance_threshold' => 'nullable|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'     => 'Fund name is required.',
            'gl_code.required'  => 'GL code is required.',
            'gl_code.unique'    => 'This GL code is already in use.',
        ];
    }
}
