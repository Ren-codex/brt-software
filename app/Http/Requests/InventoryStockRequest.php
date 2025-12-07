<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InventoryStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'received_item_id' => 'required|exists:received_items,id',
            'quantity' => 'required|integer|min:1',
        ];
    }
}
