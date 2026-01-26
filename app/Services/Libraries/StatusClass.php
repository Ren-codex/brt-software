<?php

namespace App\Services\Libraries;


use App\Models\ListStatus;
use App\Http\Resources\Libraries\ListStatusResource;


class StatusClass
{
    public function lists($request){
        $data = ListStatusResource::collection(
            ListStatus::when($request->keyword, function ($query,$keyword) {
                    $query->where('name', 'LIKE', "%{$keyword}%")
                          ->orWhere('description', 'LIKE', "%{$keyword}%")
                          ->orWhere('text_color', 'LIKE', "%{$keyword}%")
                          ->orWhere('bg_color', 'LIKE', "%{$keyword}%");
                })
                ->orderBy('created_at', 'DESC')
                ->paginate($request->count)
        );
        return $data;
    }

    public function save($request){

        $data = ListStatus::create([
            'name' =>  $request->name,
            'slug' => str_replace(' ', '-', strtolower($request->name)),
            'description' =>  $request->description,
            'text_color' =>  $request->text_color,
            'bg_color' =>  $request->bg_color,
        ]);

        return [
            'data' => new ListStatusResource($data),
            'message' => 'Status saved was successful!', 
            'info' => "You've successfully saved the status"
        ];
    }

    public function update($request){
        $data = ListStatus::findOrFail($request->id);
         $data->update([
            'name' =>  $request->name,
            'slug' => str_replace(' ', '-', strtolower($request->name)),
            'description' =>  $request->description,
            'text_color' =>  $request->text_color,
            'bg_color' =>  $request->bg_color,
        ]);

        return [
            'data' => new ListStatusResource($data),
            'message' => 'Status updated was successful!', 
            'info' => "You've successfully updated the status"
        ];
    }

    public function delete($id){
        $data = ListStatus::findOrFail($id);
        $data->delete();

        return [
            'data' => null,
            'message' => 'Brand deleted was successful!',
            'info' => "You've successfully deleted the brand"
        ];
    }

}
