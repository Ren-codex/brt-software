<?php

namespace App\Http\Requests\Libraries;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {

        return [
            'name' => [
                'required',
                'string',
                Rule::unique('list_suppliers', 'name')->ignore($this->id),
            ],

            'address' => 'required|string',

            'contact_person' => 'required|string',

            'contact_number' => [
                'required',
                'regex:/^09\d{9}$/',
                Rule::unique('list_suppliers', 'contact_number')->ignore($this->id),
            ],

            'email' => [
                'required',
                'email',
                Rule::unique('list_suppliers', 'email')->ignore($this->id),
            ],

            'tin' => [
                'required',
                'string',
                Rule::unique('list_suppliers', 'tin')->ignore($this->id),
            ],
        ];

    }
    public function messages()
    {
        return [
            'name.required' => 'This field is required',
            'address.required' => 'This field is required',
            'contact_person.required' => 'This field is required',
            'contact_number.required' => 'This field is required',
            'email.required' => 'This field is required',
            'tin.required' => 'This field is required',
        ];

    }


}
