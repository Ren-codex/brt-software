<?php

namespace App\Services\Modules;

use App\Models\Employee;
use App\Http\Resources\Modules\EmployeeResource;


class EmployeeClass
{
    public function lists($request){
        $data = EmployeeResource::collection(
            Employee::when($request->keyword, function ($query,$keyword) {
                    $query->where(function($q) use ($keyword) {
                        $q->whereHas('account', function($q) use ($keyword) {
                            $q->where('name', 'LIKE', "%{$keyword}%")
                              ->orWhere('email', 'LIKE', "%{$keyword}%")
                              ->orWhere('username', 'LIKE', "%{$keyword}%");
                        })
                        ->whereHas('position', function($q) use ($keyword) {
                            $q->where('title', 'LIKE', "%{$keyword}%")
                              ->orWhere('short', 'LIKE', "%{$keyword}%");
                        });
                    });
                })
                ->orderBy('created_at', 'DESC')
                ->paginate($request->count)
        );
        return $data;
    }

    public function save($request){
        $data = Employee::create([
            'firstname' => $request->firstname,
            'middlename' => $request->middlename,
            'lastname' => $request->lastname,
            'suffix' => $request->suffix,
            'email' => $request->email,
            'mobile' => $request->mobile,
            'birthdate' => $request->birthdate,
            'sex' => $request->sex,
            'religion' => $request->religion,
            'address' => $request->address,
            'position_id' => $request->position_id,
            'is_regular' => $request->is_regular,
            'is_active' => $request->is_active,
            'is_blacklisted' => $request->is_blacklisted,
            'added_by_id' => auth()->id(),
        ]);

        return [
            'data' => new EmployeeResource($data),
            'message' => 'Employee saved successfully!',
            'info' => "You've successfully saved the employee"
        ];
    }

    public function update($request){
        $data = Employee::findOrFail($request->id);
        $data->update([
            'firstname' => $request->firstname,
            'middlename' => $request->middlename,
            'lastname' => $request->lastname,
            'suffix' => $request->suffix,
            'email' => $request->email,
            'mobile' => $request->mobile,
            'birthdate' => $request->birthdate,
            'sex' => $request->sex,
            'religion' => $request->religion,
            'address' => $request->address,
            'position_id' => $request->position_id,
            'is_regular' => $request->is_regular,
            'is_active' => $request->is_active,
            'is_blacklisted' => $request->is_blacklisted,
        ]);

        return [
            'data' => new EmployeeResource($data),
            'message' => 'Employee updated successfully!',
            'info' => "You've successfully updated the employee"
        ];
    }

    public function toggleActive($request){
        $data = Employee::findOrFail($request->id);
        $data->update([
            'is_active' => $request->is_active,
        ]);

        return [
            'data' => new EmployeeResource($data),
            'message' => 'EMployee status updated successfully!',
            'info' => "You've successfully updated the employee status"
        ];
    }

    public function delete($id){
        $data = Employee::findOrFail($id);
        $data->delete();

        return [
            'data' => $data,
            'message' => 'Employee deleted was successful!',
            'info' => "You've successfully deleted the employee"
        ];
    }



}
