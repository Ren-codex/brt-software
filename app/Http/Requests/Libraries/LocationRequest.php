<?php

namespace App\Http\Requests\Libraries;

use Illuminate\Foundation\Http\FormRequest;

class LocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {

        return [
            'name' => 'required|string|unique:list_locations,name' . ($this->input('id') ? ',' . $this->input('id') : ''),
            'is_active' => 'nullable|boolean',
        ];

    }
    public function messages()
    {
        return [
            'name.required' => 'This field is required',
            'name.unique' => 'The name must be unique.',
        ];
    }

}
