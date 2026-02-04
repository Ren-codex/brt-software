<?php

namespace App\Http\Controllers\Modules;

use App\Services\DropdownClass;
use App\Traits\HandlesTransaction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Libraries\PayrollSettingClass;
use App\Http\Requests\Libraries\PayrollSettingRequest;
use App\Http\Resources\Libraries\PayrollSettingResource;

class PayrollSettingController extends Controller
{
    use HandlesTransaction;

    public $payrollSetting, $dropdown;

    public function __construct(PayrollSettingClass $payrollSetting, DropdownClass $dropdown){
        $this->dropdown = $dropdown;
        $this->payrollSetting = $payrollSetting;
    }

    public function index(Request $request){
        $payrollSetting = $this->payrollSetting->lists($request);
        return PayrollSettingResource::collection($payrollSetting);
    }

    public function update(PayrollSettingRequest $request){
        $result = $this->handleTransaction(function () use ($request) {
            return $this->payrollSetting->update($request);
        });

        return new PayrollSettingResource($result['data']);
    }
}
