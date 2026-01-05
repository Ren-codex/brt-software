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
            'title' => 'required|string|unique:list_salaries,title',
            'short' => 'required|string',
            'salary_id' => 'required|string|unique:list_salaries,id',
        ];

    }
    public function messages()
    {
        return [
            'name.required' => 'This field is required',
            'type.required' => 'This field is required',
            'salary_id.required' => 'This field is required',
        ];

    }

}
