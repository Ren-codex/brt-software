<?php

namespace App\Services\Libraries;


use App\Models\ListPosition;
use App\Http\Resources\Libraries\ListPositionResource;


class PositionClass
{
    public function lists($request){
        $data = ListPositionResource::collection(
            ListPosition::when($request->keyword, function ($query,$keyword) {
                    $query->where('title', 'LIKE', "%{$keyword}%")
                          ->orWhere('short', 'LIKE', "%{$keyword}%");
                })
                ->orderBy('created_at', 'DESC')
                ->paginate($request->count)
        );
        return $data;
    }

    public function save($request){

        $data = ListPosition::create([
            'title' =>  $request->title,
            'short' =>  $request->short,
            'salary_id' =>  $request->salary_id,
        ]);

        return [
            'data' => new ListPositionResource($data),
            'message' => 'Position saved was successful!',
            'info' => "You've successfully saved the Position"
        ];
    }

    public function update($request){
        $data = ListPosition::findOrFail($request->id);
         $data->update([
            'title' =>  $request->title,
            'short' =>  $request->short,
            'salary_id' =>  $request->salary_id,
        ]);

        return [
            'data' => new ListPositionResource($data),
            'message' => 'Position updated was successful!',
            'info' => "You've successfully updated the Position"
        ];
    }

    public function delete($id){
        $data = ListPosition::findOrFail($id);
        $data->delete();

        return [
            'data' => $data,
            'message' => 'Positon deleted was successful!',
            'info' => "You've successfully deleted the position"
        ];
    }




}
