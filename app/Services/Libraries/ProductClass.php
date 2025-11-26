<?php

namespace App\Services\Libraries;

use App\Models\Product;
use App\Http\Resources\Libraries\ProductResource;

class ProductClass
{
    public function lists($request)
    {
        $data = ProductResource::collection(
            Product::with('unit')
                ->when($request->keyword, function ($query, $keyword) {
                    $query->where('name', 'LIKE', "%{$keyword}%");
                })
                ->orderBy('created_at', 'DESC')
                ->paginate($request->count)
        );
        return $data;
    }

    public function save($request)
    {
        $data = Product::create([
            'name' => $request->name,
            'unit_id' => $request->unit_id,
        ]);

        return [
            'data' => new ProductResource($data),
            'message' => 'Product saved successfully!',
            'info' => "You've successfully saved the product"
        ];
    }

    public function update($request)
    {
        $data = Product::findOrFail($request->id);
        $data->update([
            'name' => $request->name,
            'unit_id' => $request->unit_id,
        ]);

        return [
            'data' => new ProductResource($data),
            'message' => 'Product updated successfully!',
            'info' => "You've successfully updated the product"
        ];
    }

    public function delete($id){
        $data = Product::findOrFail($id);
        $data->delete();

        return [
            'data' => $data,
            'message' => 'Product deleted was successful!',
            'info' => "You've successfully deleted the product"
        ];
    }
}
