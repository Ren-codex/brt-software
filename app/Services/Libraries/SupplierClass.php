<?php

namespace App\Services\Libraries;


use App\Models\ListSupplier;
use App\Http\Resources\Libraries\ListSupplierResource;


class SupplierClass
{
    public function lists($request){
        $data = ListSupplierResource::collection(
            ListSupplier::query()
                ->orderBy('created_at', 'DESC')
                ->paginate($request->count)
        );
        return $data;
    }

      public function save($request){

        $data = ListSupplier::create([
            'name' =>  $request->name,
            'address' =>  $request->address,
            'contact_person' =>  $request->contact_person,
            'contact_number' =>  $request->contact_number,
            'email' =>  $request->email,
            'tin' =>  $request->tin,
        ]);

        return [
            'data' => new ListSupplierResource($data),
            'message' => 'Supplier saved was successful!', 
            'info' => "You've successfully saved the supplier"
        ];
    }

    public function update($request){
        $data = ListSupplier::findOrFail($request->id);
         $data->update([
            'name' =>  $request->name,
            'address' =>  $request->address,
            'contact_person' =>  $request->contact_person,
            'contact_number' =>  $request->contact_number,
            'email' =>  $request->email,
            'tin' =>  $request->tin,
        ]);

        return [
            'data' => new ListSupplierResource($data),
            'message' => 'Supplier updated was successful!', 
            'info' => "You've successfully updated the supplier"
        ];
    }



}
