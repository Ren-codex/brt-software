<?php

namespace App\Http\Requests\Libraries;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
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
            'code'          => [
                'required',
                'string',
                'max:50',
                'regex:/^[A-Z0-9]+$/',
                Rule::unique('products', 'code')->ignore($this->id),
            ],
            'weight'     => 'required|integer|max:255',
            'unit_id'       => 'required|exists:list_units,id',
            'brand_id'      => 'required|exists:list_brands,id',
            'packaging_id'  => 'required|exists:list_packagings,id',
            'minimum_stock' => 'nullable|integer|min:0',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $existingProduct = Product::where('brand_id', $this->brand_id)
                ->where('unit_id', $this->unit_id)
                ->where('weight', $this->weight)
                ->when($this->id, fn ($query) => $query->where('id', '!=', $this->id))
                ->first();

            if ($existingProduct) {
                $validator->errors()->add('duplicate', 'A product with this brand, unit, and pack size already exists.');
            }
        });
    }
}
