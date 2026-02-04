<?php

namespace App\Services\Libraries;

use App\Models\PayrollSetting;
use App\Http\Resources\Libraries\PayrollSettingResource;
use Illuminate\Support\Facades\Auth;
use App\Models\PayrollSettingLog;
use Illuminate\Support\Facades\DB;

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
        try{
            db::beginTransaction();

            $data = PayrollSetting::findOrFail($request->id);
            $oldValue = $data->value;

            $data->update([
                'value' => $request->value,
            ]);

            PayrollSettingLog::create([
                'changed_data' => "Updated payroll setting '{$data->field_name}' from {$oldValue} → {$request->value}",
                'updated_by_id' => Auth::user()->id,
                'payroll_setting_id' => $data->id,
            ]);

            db::commit();

            return [
                'data' => new PayrollSettingResource($data),
                'message' => 'Payroll setting updated was successful!',
                'info' => "You've successfully updated the payroll setting"
            ];
        }catch(\Exception $e){
            return [
                'error' => true,
                'message' => 'An error occurred while updating the payroll setting.',
                'info' => $e->getMessage()
            ];
        }
    }
}
