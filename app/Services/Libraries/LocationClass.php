<?php

namespace App\Services\Libraries;


use App\Models\ListLocation;
use App\Http\Resources\Libraries\ListLocationResource;


class LocationClass
{
    public function lists($request){
        $data = ListLocationResource::collection(
            ListLocation::when($request->keyword, function ($query,$keyword) {
                    $query->where('name', 'LIKE', "%{$keyword}%");
                })
                ->orderBy('created_at', 'DESC')
                ->paginate($request->count)
        );
        return $data;
    }

    public function save($request){

        $data = ListLocation::create([
            'name' =>  $request->name,
            'is_active' => $request->is_active ?? true,
        ]);

        return [
            'data' => new ListLocationResource($data),
            'message' => 'Location saved was successful!', 
            'info' => "You've successfully saved the location"
        ];
    }

    public function update($request){
        $data = ListLocation::findOrFail($request->id);
         $data->update([
            'name' =>  $request->name,
            'is_active' => $request->is_active ?? true,
        ]);

        return [
            'data' => new ListLocationResource($data),
            'message' => 'Location updated was successful!', 
            'info' => "You've successfully updated the location"
        ];
    }

    public function delete($id){
        $data = ListLocation::findOrFail($id);
        $data->delete();

        return [
            'data' => null,
            'message' => 'Location deleted was successful!',
            'info' => "You've successfully deleted the location"
        ];
    }

}
