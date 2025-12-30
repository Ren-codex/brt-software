<?php

namespace App\Http\Requests\Modules;

use Illuminate\Foundation\Http\FormRequest;

class SalesOrderRequest extends FormRequest
{

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {

        return [
            'order_date' => 'required|date',
            'payment_mode' => 'required|string',
            'payment_term' => 'required|string',
            'customer_id' => 'required|exists:customers,id',
            
        ];

    }
    public function messages()
    {
        return [
            'order_date.required' => 'This field is required',
            'payment_mode.required' => 'This field is required',
            'payment_term.required' => 'This field is required',
            'customer_id.required' => 'This field is required',
        ];

    }

}
