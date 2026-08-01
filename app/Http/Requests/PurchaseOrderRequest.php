<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'total_amount' => 'required|numeric|min:0|max:9999999999999.99',
            'supplier_id' => 'required|exists:list_suppliers,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1|max:2147483647',
            'items.*.unit_cost' => 'required|numeric|min:0|max:99999999.99',
            'items.*.total_cost' => 'required|numeric|min:0|max:9999999999999.99',
        ];
    }

    public function messages(): array
    {
        return [
            'items.*.quantity.max' => 'Quantity is too large.',
            'items.*.unit_cost.max' => 'Unit cost must not exceed 99,999,999.99.',
            'items.*.total_cost.max' => 'Total cost per item must not exceed 9,999,999,999,999.99.',
            'total_amount.max' => 'Total amount is too large.',
        ];
    }
}
