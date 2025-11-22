<?php

namespace App\Http\Requests\Libraries;

use Illuminate\Foundation\Http\FormRequest;

class StatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {

        return [
            'name' => 'required|string|unique:list_statuses,name' . ($this->input('id') ? ',' . $this->input('id') : ''),
            'text_color' => 'required|string',
            'bg_color' => 'required|string',
        ];

    }
    public function messages()
    {
        return [
            'name.required' => 'This field is required',
            'name.unique' => 'The name must be unique.',
            'text_color.required' => 'This field is required',
            'bg_color.required' => 'This field is required',
        ];

    }

}
