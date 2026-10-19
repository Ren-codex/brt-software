<?php

namespace App\Http\Requests\Modules;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerRequest extends FormRequest
{

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {

        return [
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255|unique:customers,email,' . $this->id,
            // Optional: not every customer has one on file.
            'contact_number' => [
                    'nullable',
                    'regex:/^09\d{9}$/',
                    Rule::unique('customers', 'contact_number')->ignore($this->id),
            ],
            'address'      => 'required|string|max:255',
            'credit_limit' => 'nullable|numeric|min:0',
            'is_regular' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'is_blacklisted' => 'nullable|boolean',
        ];

    }
    public function messages()
    {
        return [
            'name.required' => 'Please enter the customer name.',
            'name.max' => 'The customer name is too long (255 characters at most).',
            'address.required' => 'Please enter the address.',
            'address.max' => 'The address is too long (255 characters at most).',
            'email.email' => 'Please enter a valid email address, e.g. name@example.com.',
            'email.unique' => 'Another customer already uses this email.',
            'contact_number.regex' => 'The contact number must be 11 digits starting with 09, e.g. 09171234567. Leave it blank if there is none.',
            'contact_number.unique' => 'Another customer already uses this contact number.',
            'credit_limit.numeric' => 'The credit limit must be a number.',
            'credit_limit.min' => 'The credit limit cannot be negative.',
        ];

    }

}
