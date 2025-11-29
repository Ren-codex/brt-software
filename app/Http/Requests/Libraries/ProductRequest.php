<?php

namespace App\Http\Requests\Libraries;

use Illuminate\Foundation\Http\FormRequest;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|unique:products,name' . ($this->input('id') ? ',' . $this->input('id') : ''),
            'unit_id' => 'required|exists:list_units,id',
        ];
    }

    public function messages()
    {
        return [
            'name.required' => 'This field is required',
            'name.unique' => 'The name must be unique.',
            'unit_id.required' => 'Please select a unit',
            'unit_id.exists' => 'Selected unit is invalid',
        ];
    }
}
