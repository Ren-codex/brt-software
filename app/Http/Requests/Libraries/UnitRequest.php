<?php

namespace App\Http\Requests\Libraries;

use Illuminate\Foundation\Http\FormRequest;

class UnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {

        return [
            'name' => 'required|string',
        ];

    }
    public function messages()
    {
        return [
            'name.required' => 'This field is required',
        ];

    }

}
