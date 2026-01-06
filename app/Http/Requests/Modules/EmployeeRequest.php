<?php

namespace App\Http\Requests\Modules;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeRequest extends FormRequest
{

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {

        return [
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255|unique:employees,email,' . $this->id,
            'contact_number' => [
                    'required',
                    'regex:/^09\d{9}$/',
                    Rule::unique('employees', 'contact_number')->ignore($this->id),
            ],
            'address' => 'required|string|max:500',
        ];

    }
    public function messages()
    {
        return [
            'name.required' => 'This field is required',
            'email.email' => 'Please provide a valid email address',
            'email.unique' => 'This email is already taken',
            'contact_number.required' => 'This field is required',
        ];

    }

}
