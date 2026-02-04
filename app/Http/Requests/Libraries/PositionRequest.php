<?php

namespace App\Http\Requests\Libraries;

use Illuminate\Foundation\Http\FormRequest;

class PositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {

        return [
            'title' => 'required|string|unique:list_positions,title' . ($this->input('id') ? ',' . $this->input('id') : ''),
            'rate_per_day' => 'required|numeric',
        ];

    }
    public function messages()
    {
        return [
            'title.required' => 'This field is required',
            'rate_per_day.required' => 'This field is required',
        ];

    }

}
