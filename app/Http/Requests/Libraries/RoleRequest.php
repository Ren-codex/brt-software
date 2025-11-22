<?php

namespace App\Http\Requests\Libraries;

use Illuminate\Foundation\Http\FormRequest;

class RoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {

        return [
            'name' => 'required|string',
            'type' => 'required|string',
            'definition' => 'required|string',
        ];

    }
    public function messages()
    {
        return [
            'name.required' => 'This field is required',
            'type.required' => 'This field is required',
            'definition.required' => 'This field is required',
        ];

    }

}
