<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductConversionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'source_stock_id'    => 'required|exists:inventory_stocks,id',
            'product_id'         => 'required|exists:products,id',
            'source_qty_used'    => 'required|integer|min:1',
            'conversion_ratio'   => 'required|numeric|min:0.0001',
            'weight_loss_ids'    => 'nullable|array',
            'weight_loss_ids.*'  => 'integer|exists:inventory_weight_losses,id',
            'retail_price'       => 'nullable|numeric|min:0',
            'wholesale_price'    => 'nullable|numeric|min:0',
            'expiration_date'    => 'nullable|date',
            'reason'             => 'nullable|string|max:500',
        ];
    }

    public function attributes(): array
    {
        return [
            'source_stock_id'  => 'source batch',
            'product_id'       => 'target product',
            'source_qty_used'  => 'quantity to convert',
            'conversion_ratio' => 'conversion ratio',
            'retail_price'     => 'retail price',
            'wholesale_price'  => 'wholesale price',
            'expiration_date'  => 'expiration date',
        ];
    }
}
