<?php

namespace App\Services\Libraries;

use App\Models\PayrollSetting;
use App\Http\Resources\Libraries\PayrollSettingResource;

class PayrollSettingClass
{
    public function lists($request){
        $data = PayrollSettingResource::collection(
            PayrollSetting::when($request->keyword, function ($query,$keyword) {
                    $query->where('field_name', 'LIKE', "%{$keyword}%")
                          ->orWhere('formula', 'LIKE', "%{$keyword}%")
                          ->orWhere('value', 'LIKE', "%{$keyword}%");
                })
                ->orderBy('created_at', 'DESC')
                ->paginate($request->count)
        );
        return $data;
    }

    public function update($request){
        $data = PayrollSetting::findOrFail($request->id);
        $data->update([
            'value' => $request->value,
        ]);

        return [
            'data' => new PayrollSettingResource($data),
            'message' => 'Payroll setting updated was successful!',
            'info' => "You've successfully updated the payroll setting"
        ];
    }
}
