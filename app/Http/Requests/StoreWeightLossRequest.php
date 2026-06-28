<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWeightLossRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'inventory_stock_id'     => 'required|exists:inventory_stocks,id',
            'items'                  => 'required|array|min:1',
            'items.*.affected_sacks' => 'required|integer|min:1',
            'items.*.loss_per_sack'  => 'required|numeric|min:0.01',
            'reason'                 => 'required|string|max:100',
            'notes'                  => 'nullable|string|max:500',
            'recorded_at'            => 'required|date',
        ];
    }

    public function attributes(): array
    {
        return [
            'items.*.affected_sacks' => 'short sacks',
            'items.*.loss_per_sack'  => 'loss per sack',
            'recorded_at'            => 'date',
        ];
    }
}
