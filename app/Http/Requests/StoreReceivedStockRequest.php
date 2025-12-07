<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReceivedStockRequest extends FormRequest
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
            'po_id' => 'required|exists:purchase_orders,id',
            'supplier_id' => 'required|exists:list_suppliers,id',
            'received_date' => 'required|date',
            'batch_code' => 'required|string|max:255',
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0',
            'items.*.unit_cost' => 'required|numeric|min:0',
            'items.*.total_cost' => 'required|numeric|min:0',
            'items.*.po_item_id' => 'required|exists:purchase_order_items,id',
        ];
    }
}
