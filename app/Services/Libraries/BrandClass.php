<?php

namespace App\Services\Libraries;


use App\Models\ListBrand;
use App\Http\Resources\Libraries\ListBrandResource;


class BrandClass
{
    public function lists($request){
        $data = ListBrandResource::collection(
            ListBrand::when($request->keyword, function ($query,$keyword) {
                    $query->where('name', 'LIKE', "%{$keyword}%");
                })
                ->orderBy('created_at', 'DESC')
                ->paginate($request->count)
        );
        return $data;
    }

    public function save($request){

        $data = ListBrand::create([
            'name' =>  $request->name,
        ]);

        return [
            'data' => new ListBrandResource($data),
            'message' => 'Brand saved was successful!', 
            'info' => "You've successfully saved the supplier"
        ];
    }

    public function update($request){
        $data = ListBrand::findOrFail($request->id);
         $data->update([
            'name' =>  $request->name,
        ]);

        return [
            'data' => new ListBrandResource($data),
            'message' => 'Brand updated was successful!', 
            'info' => "You've successfully updated the supplier"
        ];
    }

    public function delete($id){
        $data = ListBrand::findOrFail($id);
        $data->delete();

        return [
            'data' => null,
            'message' => 'Brand deleted was successful!',
            'info' => "You've successfully deleted the brand"
        ];
    }

}
