<?php

namespace App\Services\Libraries;

use App\Models\ListPackaging;
use App\Http\Resources\Libraries\ListPackagingResource;

class PackagingClass
{
    public function lists($request)
    {
        $data = ListPackagingResource::collection(
            ListPackaging::when($request->keyword, function ($query, $keyword) {
                    $query->where('name', 'LIKE', "%{$keyword}%")
                          ->orWhere('description', 'LIKE', "%{$keyword}%");
                })
                ->orderBy('created_at', 'DESC')
                ->paginate($request->count)
        );

        return $data;
    }

    public function save($request)
    {
        $data = ListPackaging::create([
            'name'        => $request->name,
            'description' => $request->description,
        ]);

        return [
            'data'    => new ListPackagingResource($data),
            'message' => 'Packaging saved was successful!',
            'info'    => "You've successfully saved the packaging",
        ];
    }

    public function update($request)
    {
        $data = ListPackaging::findOrFail($request->id);
        $data->update([
            'name'        => $request->name,
            'description' => $request->description,
        ]);

        return [
            'data'    => new ListPackagingResource($data),
            'message' => 'Packaging updated was successful!',
            'info'    => "You've successfully updated the packaging",
        ];
    }

    public function delete($id)
    {
        $data = ListPackaging::findOrFail($id);
        $data->delete();

        return [
            'data'    => null,
            'message' => 'Packaging deleted was successful!',
            'info'    => "You've successfully deleted the packaging",
        ];
    }
}
