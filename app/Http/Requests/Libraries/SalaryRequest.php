<?php

namespace App\Http\Requests\Libraries;

use Illuminate\Foundation\Http\FormRequest;

class SalaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {

        return [
            'amount' => 'required',
        ];

    }
    public function messages()
    {
        return [
            'amount.required' => 'This field is required',
        ];

    }

}
