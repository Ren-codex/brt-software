<?php

namespace App\Http\Requests\Libraries;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Product;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pack_size' => 'required|integer|max:255',
            'unit_id' => 'required|exists:list_units,id',
            'brand_id' => 'required|exists:list_brands,id',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $existingProduct = Product::where('brand_id', $this->brand_id)
                ->where('unit_id', $this->unit_id)
                ->where('pack_size', $this->pack_size)
                ->first();

            if ($existingProduct) {
                $validator->errors()->add('duplicate', 'A product with this brand, unit, and pack size already exists.');
            }
        });
    }
}
