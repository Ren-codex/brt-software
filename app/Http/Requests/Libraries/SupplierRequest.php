<?php

namespace App\Http\Requests\Libraries;

use Illuminate\Foundation\Http\FormRequest;

class SupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {

        return [
            'name' => 'required|string',
            'address' => 'required|string',
            'contact_person' => 'required|string',
            'contact_number' => 'required|string',
            'email' => 'required|email',
            'tin' => 'required|string',
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
