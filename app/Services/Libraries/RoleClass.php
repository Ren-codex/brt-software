<?php

namespace App\Services\Libraries;


use App\Models\ListRole;
use App\Http\Resources\Libraries\ListRoleResource;


class RoleClass
{
    public function lists($request){
        $data = ListRoleResource::collection(
            ListRole::when($request->keyword, function ($query,$keyword) {
                    $query->where('name', 'LIKE', "%{$keyword}%")
                          ->orWhere('name', 'LIKE', "%{$keyword}%")
                          ->orWhere('type', 'LIKE', "%{$keyword}%")
                          ->orWhere('definition', 'LIKE', "%{$keyword}%");
                })
                ->orderBy('created_at', 'DESC')
                ->paginate($request->count)
        );
        return $data;
    }

    public function save($request){

        $data = ListRole::create([
            'name' =>  $request->name,
            'type' =>  $request->type,
            'definition' =>  $request->definition,
        ]);

        return [
            'data' => new ListRoleResource($data),
            'message' => 'Role saved was successful!', 
            'info' => "You've successfully saved the role"
        ];
    }

    public function update($request){
        $data = ListRole::findOrFail($request->id);
         $data->update([
            'name' =>  $request->name,
            'type' =>  $request->type,
            'definition' =>  $request->definition,
        ]);

        return [
            'data' => new ListRoleResource($data),
            'message' => 'Role updated was successful!', 
            'info' => "You've successfully updated the role"
        ];
    }

    public function delete($id){
        $data = ListRole::findOrFail($id);
        $data->delete();

        return [
            'data' => $data,
            'message' => 'Role deleted was successful!',
            'info' => "You've successfully deleted the role"
        ];
    }




}
