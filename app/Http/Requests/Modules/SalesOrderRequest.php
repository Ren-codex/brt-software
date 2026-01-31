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
            'customer_id' => 'required|exists:customers,id',
            'assigned_to' => 'nullable|exists:employees,id',
            'payment_mode' => 'required|string|in:Cash Sales,Credit Sales',
            'payment_term' => 'required|string|in:cash,credit',
            'due_date' => 'nullable|date|required_if:payment_term,credit',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_cost' => 'required|numeric|min:0',
            'items.*.batch_code' => 'required|string|exists:received_stocks,batch_code',
            'items.*.discount_per_unit' => 'nullable|numeric|min:0',

        ];

    }
    public function messages()
    {
        return [
            'order_date.required' => 'This field is required',
            'customer_id.required' => 'This field is required',
        ];

    }

}
