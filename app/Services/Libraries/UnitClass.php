<?php

namespace App\Services\Libraries;


use App\Models\ListUnit;
use App\Http\Resources\Libraries\ListUnitResource;


class UnitClass
{
    public function lists($request){
        $data = ListUnitResource::collection(
            ListUnit::when($request->keyword, function ($query,$keyword) {
                    $query->where('name', 'LIKE', "%{$keyword}%")
                          ->orWhere('description', 'LIKE', "%{$keyword}%");
                })
                ->orderBy('created_at', 'DESC')
                ->paginate($request->count)
        );
        return $data;
    }

    public function save($request){

        $data = ListUnit::create([
            'name' =>  $request->name,
            'description' =>  $request->description,
        ]);

        return [
            'data' => new ListUnitResource($data),
            'message' => 'Unit saved was successful!',
            'info' => "You've successfully saved the unit"
        ];
    }

    public function update($request){
        $data = ListUnit::findOrFail($request->id);
         $data->update([
            'name' =>  $request->name,
            'description' =>  $request->description,
        ]);

        return [
            'data' => new ListUnitResource($data),
            'message' => 'Unit updated was successful!',
            'info' => "You've successfully updated the unit"
        ];
    }

    public function delete($id){
        $data = ListUnit::findOrFail($id);
        $data->delete();

        return [
            'data' => null,
            'message' => 'Unit deleted was successful!',
            'info' => "You've successfully deleted the unit"
        ];
    }

}
