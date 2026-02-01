<?php

namespace App\Http\Controllers\Modules;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\PayrollRequest;
use App\Services\DropdownClass;
use App\Services\Modules\PayrollClass;

class PayrollController extends Controller
{
    public $employee,$dropdown, $payroll;

    public function __construct(DropdownClass $dropdown, PayrollClass $payroll){
        $this->dropdown = $dropdown;
        $this->payroll = $payroll;
    }

    public function index(Request $request){
        switch($request->option){
            case 'lists':
                $payrolls = $this->payroll->lists($request);
                return response()->json($payrolls);
            break;
            default:
                return inertia('Modules/Payroll/Index', [
                    'dropdowns' => [
                        'employees' => $this->dropdown->employees(),
                        'payroll_settings' => $this->dropdown->payroll_settings(),
                        'payroll_templates' => $this->dropdown->payroll_templates(),
                    ]
                ]);
            break;
        }
    }

    public function store(PayrollRequest $request)
    {
        $result = $this->payroll->store($request);
        return response()->json($result);
    }

    public function show(Request $request, $id)
    {
        $payroll = $this->payroll->show($id);
        return response()->json($payroll);
    }

    public function update(PayrollRequest $request, $id)
    {
        $result = $this->payroll->update($request, $id);
        return response()->json($result);
    }

    public function destroy($id)
    {
        $result = $this->payroll->destroy($id);
        return response()->json($result);
    }
}
