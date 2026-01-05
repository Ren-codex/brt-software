<?php

namespace App\Services\Libraries;


use App\Models\ListSalary;
use App\Http\Resources\Libraries\ListSalaryResource;


class SalaryClass
{
    public function lists($request){
        $data = ListSalaryResource::collection(
            ListSalary::when($request->keyword, function ($query,$keyword) {
                    $query->where('name', 'LIKE', "%{$keyword}%")
                          ->orWhere('title', 'LIKE', "%{$keyword}%")
                          ->orWhere('short', 'LIKE', "%{$keyword}%");
                })
                ->orderBy('created_at', 'DESC')
                ->paginate($request->count)
        );
        return $data;
    }

    public function save($request){

        $data = ListSalary::create([
            'amount' =>  $request->amount,
        ]);

        return [
            'data' => new ListSalaryResource($data),
            'message' => 'Salary saved was successful!',
            'info' => "You've successfully saved the Salary"
        ];
    }

    public function update($request){
        $data = ListSAlary::findOrFail($request->id);
         $data->update([
            'name' =>  $request->name,
            'type' =>  $request->type,
            'definition' =>  $request->definition,
        ]);

        return [
            'data' => new ListSalaryResource($data),
            'message' => 'Salary updated was successful!', 
            'info' => "You've successfully updated the Salary"
        ];
    }

    public function delete($id){
        $data = ListSalary::findOrFail($id);
        $data->delete();

        return [
            'data' => $data,
            'message' => 'Salary deleted was successful!',
            'info' => "You've successfully deleted the salary"
        ];
    }




}
