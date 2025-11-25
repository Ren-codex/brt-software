<?php

namespace App\Services\Libraries;


use App\Models\ListSupplier;
use App\Http\Resources\Libraries\ListSupplierResource;


class SupplierClass
{
    public function lists($request){
        $data = ListSupplierResource::collection(
            ListSupplier::when($request->keyword, function ($query,$keyword) {
                    $query->where('name', 'LIKE', "%{$keyword}%")
                          ->orWhere('address', 'LIKE', "%{$keyword}%")
                          ->orWhere('contact_person', 'LIKE', "%{$keyword}%")
                          ->orWhere('contact_number', 'LIKE', "%{$keyword}%")
                          ->orWhere('email', 'LIKE', "%{$keyword}%")
                          ->orWhere('tin', 'LIKE', "%{$keyword}%");
                })
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
            'status_id' =>  1,
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
            'is_active' =>  $request->is_active,
            'is_blacklisted' =>  $request->is_blacklisted,
        ]);

        return [
            'data' => new ListSupplierResource($data),
            'message' => 'Supplier updated was successful!', 
            'info' => "You've successfully updated the supplier"
        ];
    }



}
