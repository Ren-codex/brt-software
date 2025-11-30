<?php

namespace App\Services\Modules;


use App\Models\Customer;
use App\Http\Resources\Modules\CustomerResource;


class CustomerClass
{
    public function lists($request){
        $data = CustomerResource::collection(
            Customer::when($request->keyword, function ($query,$keyword) {
                    $query->where(function($q) use ($keyword) {
                        $q->where('name', 'LIKE', "%{$keyword}%")
                          ->orWhere('address', 'LIKE', "%{$keyword}%")
                          ->orWhere('contact_number', 'LIKE', "%{$keyword}%")
                          ->orWhere('email', 'LIKE', "%{$keyword}%");
                    });
                })
                ->orderBy('created_at', 'DESC')
                ->paginate($request->count)
        );
        return $data;
    }

    public function save($request){
        $data = Customer::create([
            'name' => $request->name,
            'address' => $request->address,
            'contact_number' => $request->contact_number,
            'email' => $request->email,
            'is_active' => $request->is_active,
            'is_regular' => $request->is_regular,
            'is_blacklisted' => $request->is_blacklisted,
            'added_by_id' => auth()->id(),
        ]);

        return [
            'data' => new CustomerResource($data),
            'message' => 'Customer saved successfully!',
            'info' => "You've successfully saved the customer"
        ];
    }

    public function update($request){
        $data = Customer::findOrFail($request->id);
        $data->update([
            'name' => $request->name,
            'address' => $request->address,
            'contact_number' => $request->contact_number,
            'email' => $request->email,
            'is_active' => $request->is_active,
            'is_regular' => $request->is_regular,
            'is_blacklisted' => $request->is_blacklisted,
        ]);

        return [
            'data' => new CustomerResource($data),
            'message' => 'Customer updated successfully!',
            'info' => "You've successfully updated the customer"
        ];
    }

    public function toggleActive($request){
        $data = Customer::findOrFail($request->id);
        $data->update([
            'is_active' => $request->is_active,
        ]);

        return [
            'data' => new CustomerResource($data),
            'message' => 'Customer status updated successfully!',
            'info' => "You've successfully updated the customer status"
        ];
    }

    public function delete($id){
        $data = Customer::findOrFail($id);
        $data->delete();

        return [
            'data' => $data,
            'message' => 'Customer deleted was successful!',
            'info' => "You've successfully deleted the customer"
        ];
    }



}
