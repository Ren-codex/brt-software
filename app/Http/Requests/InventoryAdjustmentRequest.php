<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InventoryAdjustmentRequest extends FormRequest
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
            'inventory_stocks_id' => 'required|exists:inventory_stocks,id',
            'new_quantity' => 'required|integer|min:1',
            'type' => 'required|string',
            'previous_quantity' => 'required|integer',
            'reason' => 'nullable|string|max:255',
        ];
    }
}
